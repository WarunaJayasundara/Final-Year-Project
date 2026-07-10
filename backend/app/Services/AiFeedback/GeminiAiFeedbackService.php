<?php

namespace App\Services\AiFeedback;

use App\Contracts\AiFeedbackServiceInterface;
use App\Models\Question;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Real Gemini-backed explanation generator. Not active until
 * AI_FEEDBACK_DRIVER=gemini and GEMINI_API_KEY are set in .env - see
 * AppServiceProvider::register() for the driver binding. Falls back to the
 * mock service's wording if the API call fails, so a missing/invalid key
 * never breaks the student-facing report page.
 */
class GeminiAiFeedbackService implements AiFeedbackServiceInterface
{
    private const MODEL = 'gemini-1.5-flash';

    private Client $client;

    private MockAiFeedbackService $fallback;

    // PHP 8.0 (this project's XAMPP-pinned version) doesn't support "new in
    // initializers" (PHP 8.1+), so defaults are resolved in the body instead
    // of the constructor signature.
    public function __construct(?Client $client = null, ?MockAiFeedbackService $fallback = null)
    {
        $this->client = $client ?? new Client();
        $this->fallback = $fallback ?? new MockAiFeedbackService();
    }

    public function explainAnswer(Question $question, string $selectedOptionKey, string $locale): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return $this->fallback->explainAnswer($question, $selectedOptionKey, $locale);
        }

        try {
            $parts = [['text' => $this->buildPrompt($question, $selectedOptionKey, $locale)]];

            if ($question->image_path && Storage::disk('public')->exists($question->image_path)) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => Storage::disk('public')->mimeType($question->image_path) ?: 'image/png',
                        'data' => base64_encode(Storage::disk('public')->get($question->image_path)),
                    ],
                ];
            }

            $response = $this->client->post(
                sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', self::MODEL, $apiKey),
                [
                    'json' => [
                        'contents' => [['parts' => $parts]],
                    ],
                    'timeout' => 15,
                ]
            );

            $body = json_decode((string) $response->getBody(), true);
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            return $text ?: $this->fallback->explainAnswer($question, $selectedOptionKey, $locale);
        } catch (\Throwable $e) {
            Log::warning('Gemini AI feedback call failed, falling back to mock explanation.', [
                'question_id' => $question->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->explainAnswer($question, $selectedOptionKey, $locale);
        }
    }

    private function buildPrompt(Question $question, string $selectedOptionKey, string $locale): string
    {
        $options = collect($question->options)->keyBy('key');
        $selected = $options->get($selectedOptionKey);
        $correct = $options->get($question->correct_option_key);
        $languageName = $locale === 'si' ? 'Sinhala' : 'English';

        $questionText = $locale === 'si' ? $question->question_text_si : $question->question_text_en;
        $selectedText = $selected["text_{$locale}"] ?? $selected['text_en'] ?? $selectedOptionKey;
        $correctText = $correct["text_{$locale}"] ?? $correct['text_en'] ?? $question->correct_option_key;

        return <<<PROMPT
        You are a friendly IQ-training tutor helping a Sri Lankan student improve their reasoning skills.

        Question: {$questionText}
        The student selected: {$selectedText}
        The correct answer is: {$correctText}

        In 2-4 short sentences, written in {$languageName}, explain why the correct answer is right and
        (if the student was wrong) what pattern or reasoning step they likely missed. Be encouraging and
        concise, suitable for display directly to the student after a practice session.
        PROMPT;
    }
}

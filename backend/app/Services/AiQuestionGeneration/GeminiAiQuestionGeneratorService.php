<?php

namespace App\Services\AiQuestionGeneration;

use App\Contracts\AiQuestionGeneratorServiceInterface;
use App\Models\Category;
use App\Models\IqLevel;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Real Gemini-backed question generator. Not active until
 * AI_QUESTION_GENERATOR_DRIVER=gemini and GEMINI_API_KEY are set - see
 * AppServiceProvider::register() for the driver binding. Falls back to the
 * mock generator if the API call fails or returns a malformed response, so
 * a missing/invalid key or a bad model response never breaks the admin
 * generation flow.
 */
class GeminiAiQuestionGeneratorService implements AiQuestionGeneratorServiceInterface
{
    private const MODEL = 'gemini-1.5-flash';

    /**
     * Bloom's Taxonomy verb the question should target, scaled by IQ level -
     * lower levels test recall/comprehension, higher levels test analysis
     * and evaluation, matching standard educational assessment design.
     */
    private const BLOOM_LEVEL = [
        1 => 'Remember (simple recall)',
        2 => 'Understand (basic comprehension)',
        3 => 'Apply (use a rule or procedure)',
        4 => 'Analyze (break down a relationship or pattern)',
        5 => 'Evaluate (judge between plausible options)',
    ];

    private Client $client;

    private MockAiQuestionGeneratorService $fallback;

    // PHP 8.0 (this project's XAMPP-pinned version) doesn't support "new in
    // initializers" (PHP 8.1+), so defaults are resolved in the body instead
    // of the constructor signature.
    public function __construct(?Client $client = null, ?MockAiQuestionGeneratorService $fallback = null)
    {
        $this->client = $client ?? new Client();
        $this->fallback = $fallback ?? new MockAiQuestionGeneratorService();
    }

    public function generate(Category $category, IqLevel $level, ?string $examCategoryLabel, array $avoidQuestionTexts): array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return $this->fallback->generate($category, $level, $examCategoryLabel, $avoidQuestionTexts);
        }

        try {
            $response = $this->client->post(
                sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', self::MODEL, $apiKey),
                [
                    'json' => [
                        'contents' => [['parts' => [['text' => $this->buildPrompt($category, $level, $examCategoryLabel, $avoidQuestionTexts)]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ],
                    'timeout' => 20,
                ]
            );

            $body = json_decode((string) $response->getBody(), true);
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            $parsed = $text ? json_decode($text, true) : null;

            if (! $this->isValidDraft($parsed)) {
                Log::warning('Gemini question generation returned an invalid shape, falling back to mock.', ['category' => $category->code]);

                return $this->fallback->generate($category, $level, $examCategoryLabel, $avoidQuestionTexts);
            }

            $parsed['difficulty_weight'] = max(1, min(3, (int) ($parsed['difficulty_weight'] ?? 2)));

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('Gemini question generation call failed, falling back to mock.', [
                'category' => $category->code,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->generate($category, $level, $examCategoryLabel, $avoidQuestionTexts);
        }
    }

    private function buildPrompt(Category $category, IqLevel $level, ?string $examCategoryLabel, array $avoidQuestionTexts): string
    {
        $bloomHint = self::BLOOM_LEVEL[$level->level_number] ?? self::BLOOM_LEVEL[3];
        $examContext = $examCategoryLabel
            ? "Style the question to resemble what would appear on a {$examCategoryLabel} aptitude/reasoning paper."
            : 'Style the question as a general cognitive-assessment item, not tied to any specific exam.';
        $avoidList = $avoidQuestionTexts
            ? "Do not repeat or closely paraphrase any of these existing questions:\n- " . implode("\n- ", array_slice($avoidQuestionTexts, 0, 15))
            : 'There are no existing questions to avoid duplicating yet.';

        return <<<PROMPT
        You are writing one multiple-choice cognitive-assessment question for the
        "{$category->name_en}" category, at IQ Level {$level->level_number} of 5.

        Target cognitive skill (Bloom's Taxonomy): {$bloomHint}.
        {$examContext}
        {$avoidList}

        Respond with ONLY a JSON object (no markdown fences, no commentary) matching
        exactly this shape:
        {
          "question_text_en": "...",
          "question_text_si": "... (natural Sinhala translation, not transliteration)",
          "options": [
            {"key": "A", "text_en": "...", "text_si": "..."},
            {"key": "B", "text_en": "...", "text_si": "..."},
            {"key": "C", "text_en": "...", "text_si": "..."},
            {"key": "D", "text_en": "...", "text_si": "..."}
          ],
          "correct_option_key": "A" | "B" | "C" | "D",
          "explanation_en": "brief explanation of the correct answer",
          "explanation_si": "same explanation in Sinhala",
          "difficulty_weight": 1 | 2 | 3
        }

        Exactly one option must be correct and unambiguous. Keep the question
        self-contained (no reference to an image). Do not include any text
        outside the JSON object.
        PROMPT;
    }

    private function isValidDraft(mixed $parsed): bool
    {
        if (! is_array($parsed)) {
            return false;
        }

        $requiredKeys = ['question_text_en', 'question_text_si', 'options', 'correct_option_key', 'explanation_en', 'explanation_si'];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $parsed)) {
                return false;
            }
        }

        if (! is_array($parsed['options']) || count($parsed['options']) !== 4) {
            return false;
        }

        $keys = array_column($parsed['options'], 'key');
        if (array_diff(['A', 'B', 'C', 'D'], $keys) !== []) {
            return false;
        }

        return in_array($parsed['correct_option_key'], ['A', 'B', 'C', 'D'], true);
    }
}

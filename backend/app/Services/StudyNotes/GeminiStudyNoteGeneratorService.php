<?php

namespace App\Services\StudyNotes;

use App\Contracts\StudyNoteGeneratorServiceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Real Gemini-backed teaching-note generator. Not active until
 * AI_QUESTION_GENERATOR_DRIVER=gemini and GEMINI_API_KEY are set (reuses
 * the same driver/key as question generation - both are "Gemini reads
 * source material and writes original content" tasks). Falls back to the
 * mock generator (an honest topic index, not a fabricated summary) if the
 * API call fails or returns a malformed response.
 */
class GeminiStudyNoteGeneratorService implements StudyNoteGeneratorServiceInterface
{
    private const MODEL = 'gemini-2.5-flash';

    private const MAX_EXCERPT_CHARS = 6000;

    private Client $client;

    private MockStudyNoteGeneratorService $fallback;

    // PHP 8.0 (this project's XAMPP-pinned version) doesn't support "new in
    // initializers" (PHP 8.1+), so defaults are resolved in the body.
    public function __construct(?Client $client = null, ?MockStudyNoteGeneratorService $fallback = null)
    {
        $this->client = $client ?? new Client();
        $this->fallback = $fallback ?? new MockStudyNoteGeneratorService();
    }

    public function generate(string $documentTitle, string $textExcerpt, array $matchedTopics): array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return $this->fallback->generate($documentTitle, $textExcerpt, $matchedTopics);
        }

        try {
            $response = $this->client->post(
                sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', self::MODEL, $apiKey),
                [
                    'json' => [
                        'contents' => [['parts' => [['text' => $this->buildPrompt($documentTitle, $textExcerpt, $matchedTopics)]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ],
                    'timeout' => 25,
                ]
            );

            $body = json_decode((string) $response->getBody(), true);
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            $parsed = $text ? json_decode($text, true) : null;

            if (! $this->isValidNote($parsed)) {
                Log::warning('Gemini study-note generation returned an invalid shape, falling back to mock.', ['document' => $documentTitle]);

                return $this->fallback->generate($documentTitle, $textExcerpt, $matchedTopics);
            }

            $parsed['key_concepts'] = $parsed['key_concepts'] ?? $matchedTopics;

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('Gemini study-note generation call failed, falling back to mock.', [
                'document' => $documentTitle,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->generate($documentTitle, $textExcerpt, $matchedTopics);
        }
    }

    private function buildPrompt(string $documentTitle, string $textExcerpt, array $matchedTopics): string
    {
        // Bounded excerpt - never the full document - and an explicit
        // instruction not to reproduce it verbatim, since uploaded source
        // documents may be copyrighted commercial books or past-paper
        // compilations (see PdfIngestionService's own docblock).
        $boundedExcerpt = mb_substr($textExcerpt, 0, self::MAX_EXCERPT_CHARS);
        $topicHint = $matchedTopics !== [] ? implode(', ', $matchedTopics) : 'general aptitude reasoning';

        return <<<PROMPT
        You are writing a short bilingual (English + Sinhala) teaching/study
        note for cognitive-aptitude exam candidates, based on the theory
        concepts found in the reference material below.

        Source document title: "{$documentTitle}"
        Detected topic areas (keyword heuristic, may be imprecise): {$topicHint}

        Reference material excerpt (this may be copyrighted - use it ONLY to
        understand what concept to teach; do NOT quote, copy, or closely
        paraphrase its specific wording - explain the underlying concept in
        your own original words):
        ---
        {$boundedExcerpt}
        ---

        Respond with ONLY a JSON object (no markdown fences, no commentary)
        matching exactly this shape:
        {
          "title_en": "short descriptive title in English",
          "title_si": "natural Sinhala translation of the title",
          "learning_objective_en": "one sentence: what the student will be able to do after this lesson",
          "learning_objective_si": "the same sentence, naturally written in Sinhala",
          "content_en": "a clear, original 150-300 word teaching explanation of the underlying concept, in English",
          "content_si": "the same explanation, naturally written in Sinhala (not a transliteration)",
          "worked_example_en": "one original worked example problem with a full step-by-step solution, in English",
          "worked_example_si": "the same worked example, naturally written in Sinhala",
          "key_technique_en": "a short named technique/shortcut for solving this problem type, in English",
          "key_technique_si": "the same technique, naturally written in Sinhala",
          "common_mistakes_en": "one or two common mistakes students make with this topic, in English",
          "common_mistakes_si": "the same, naturally written in Sinhala",
          "key_concepts": ["short concept label", "another concept label"]
        }

        Do not include any text outside the JSON object.
        PROMPT;
    }

    private function isValidNote(mixed $parsed): bool
    {
        if (! is_array($parsed)) {
            return false;
        }

        foreach (['title_en', 'title_si', 'content_en', 'content_si'] as $key) {
            if (empty($parsed[$key]) || ! is_string($parsed[$key])) {
                return false;
            }
        }

        return true;
    }
}

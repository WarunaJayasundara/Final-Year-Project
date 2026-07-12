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

    /**
     * Sane bounds per authored level for the LLM-estimated solving time -
     * this project doesn't trust an LLM's raw numeric judgment blindly (see
     * the clamp applied in generate()); ResponseTimeCalibrationService later
     * replaces this authored baseline with a real learned value once enough
     * response data exists, matching the brief's own level-1-vs-level-5
     * example (~30s vs ~120s).
     */
    private const TIME_BOUNDS = [
        1 => [15, 45],
        2 => [20, 60],
        3 => [30, 80],
        4 => [40, 110],
        5 => [60, 150],
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

    public function generate(Category $category, IqLevel $level, ?string $examCategoryLabel, array $avoidQuestionTexts, ?string $sourceContext = null): array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return $this->fallback->generate($category, $level, $examCategoryLabel, $avoidQuestionTexts, $sourceContext);
        }

        try {
            $response = $this->client->post(
                sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', self::MODEL, $apiKey),
                [
                    'json' => [
                        'contents' => [['parts' => [['text' => $this->buildPrompt($category, $level, $examCategoryLabel, $avoidQuestionTexts, $sourceContext)]]]],
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

                return $this->fallback->generate($category, $level, $examCategoryLabel, $avoidQuestionTexts, $sourceContext);
            }

            $parsed['difficulty_weight'] = max(1, min(3, (int) ($parsed['difficulty_weight'] ?? 2)));
            [$minTime, $maxTime] = self::TIME_BOUNDS[$level->level_number] ?? self::TIME_BOUNDS[3];
            $parsed['solving_time_seconds'] = max($minTime, min($maxTime, (int) ($parsed['estimated_time_seconds'] ?? $minTime)));

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('Gemini question generation call failed, falling back to mock.', [
                'category' => $category->code,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->generate($category, $level, $examCategoryLabel, $avoidQuestionTexts, $sourceContext);
        }
    }

    private function buildPrompt(Category $category, IqLevel $level, ?string $examCategoryLabel, array $avoidQuestionTexts, ?string $sourceContext = null): string
    {
        $bloomHint = self::BLOOM_LEVEL[$level->level_number] ?? self::BLOOM_LEVEL[3];
        $examContext = $examCategoryLabel
            ? "Style the question to resemble what would appear on a {$examCategoryLabel} aptitude/reasoning paper."
            : 'Style the question as a general cognitive-assessment item, not tied to any specific exam.';
        $avoidList = $avoidQuestionTexts
            ? "Do not repeat or closely paraphrase any of these existing questions:\n- " . implode("\n- ", array_slice($avoidQuestionTexts, 0, 15))
            : 'There are no existing questions to avoid duplicating yet.';
        // $sourceContext is a short admin-curated summary (title + matched
        // topic keywords), never a document's raw extracted text - the
        // prompt explicitly tells the model to treat it as topic/style
        // inspiration only, never as text to reproduce, since some source
        // documents are copyrighted commercial books/past-paper compilations.
        $sourceHint = $sourceContext
            ? "Reference context (topic/style inspiration ONLY - do not reproduce or closely paraphrase any specific wording from it, it may be copyrighted material you have not seen): {$sourceContext}"
            : 'No specific reference document context was provided for this question.';
        $glossaryHint = $this->glossaryHintFor($category);
        [$minTime, $maxTime] = self::TIME_BOUNDS[$level->level_number] ?? self::TIME_BOUNDS[3];

        return <<<PROMPT
        You are writing one multiple-choice cognitive-assessment question for the
        "{$category->name_en}" category, at IQ Level {$level->level_number} of 5.

        Target cognitive skill (Bloom's Taxonomy): {$bloomHint}.
        {$examContext}
        {$sourceHint}
        {$avoidList}
        {$glossaryHint}

        Respond with ONLY a JSON object (no markdown fences, no commentary) matching
        exactly this shape:
        {
          "question_text_en": "...",
          "question_text_si": "... (natural Sinhala translation using standard Sri Lankan educational vocabulary, not a literal word-for-word or machine-style translation - use the glossary terms above where they apply)",
          "options": [
            {"key": "A", "text_en": "...", "text_si": "..."},
            {"key": "B", "text_en": "...", "text_si": "..."},
            {"key": "C", "text_en": "...", "text_si": "..."},
            {"key": "D", "text_en": "...", "text_si": "..."}
          ],
          "correct_option_key": "A" | "B" | "C" | "D",
          "explanation_en": "brief explanation of the correct answer",
          "explanation_si": "same explanation in Sinhala",
          "difficulty_weight": 1 | 2 | 3,
          "estimated_time_seconds": a realistic number of seconds a student at this level would need to solve this specific question, between {$minTime} and {$maxTime}
        }

        Exactly one option must be correct and unambiguous. Keep the question
        self-contained (no reference to an image). Do not include any text
        outside the JSON object.
        PROMPT;
    }

    /**
     * A short slice of the curated EN-SI terminology glossary (brief §15),
     * relevant to this category only, injected as prompt context - a
     * RAG-lite pattern (curated glossary + prompt template), the approach
     * this project's own methodology docs prefer over fine-tuning a custom
     * Sinhala model without sufficient legally-usable training data.
     */
    private function glossaryHintFor(Category $category): string
    {
        $domainMap = [
            'memory' => 'memory',
            'logical_reasoning' => 'logical_reasoning',
            'numerical_ability' => 'numerical_reasoning',
            'attention' => 'attention',
            'spatial_pattern' => 'spatial_reasoning',
        ];
        $domain = $domainMap[$category->code] ?? null;
        $glossaryPath = base_path('resources/sinhala_glossary.json');

        if (! $domain || ! file_exists($glossaryPath)) {
            return '';
        }

        $glossary = json_decode(file_get_contents($glossaryPath), true);
        $entries = $glossary[$domain] ?? [];
        $terms = collect($entries)
            ->merge($glossary['cognitive_training'] ?? [])
            ->take(8)
            ->map(fn ($entry) => "{$entry['en']} = {$entry['si']}")
            ->implode('; ');

        return $terms === '' ? '' : "Use this reviewed Sinhala terminology consistently where relevant: {$terms}.";
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

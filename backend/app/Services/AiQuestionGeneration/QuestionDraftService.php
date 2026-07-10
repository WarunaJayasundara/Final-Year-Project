<?php

namespace App\Services\AiQuestionGeneration;

use App\Contracts\AiQuestionGeneratorServiceInterface;
use App\Models\AiGeneratedQuestion;
use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\User;

/**
 * Orchestrates AI question generation: calls the bound generator (Gemini or
 * Mock), rejects near-duplicates of existing bank questions via a Jaccard
 * word-overlap check, and persists survivors as *drafts* - never directly
 * as live questions. Only approve() (an explicit admin action) promotes a
 * draft into the real `questions` table. This human-in-the-loop gate exists
 * because generated content feeds a real assessment instrument, where a
 * hallucinated wrong "correct answer" would silently corrupt a student's
 * ability estimate.
 */
class QuestionDraftService
{
    private const SIMILARITY_THRESHOLD = 0.6;

    private const MAX_ATTEMPTS_PER_QUESTION = 3;

    // Frame/filler words shared by nearly every question in a given
    // template family (e.g. "how many times does the letter ... appear
    // in"). Without stripping these, two questions with entirely
    // different content (different letters, numbers, sequences) still
    // share most of their tokens and score as false-positive duplicates.
    private const STOPWORDS = [
        'how', 'many', 'times', 'does', 'the', 'letter', 'appear', 'in',
        'what', 'is', 'next', 'pattern', 'comes', 'memorize', 'this',
        'sequence', 'number', 'was', 'which', 'word', 'not', 'belong',
        'with', 'others', 'and', 'a', 'an', 'of', 'to', 'from', 'for',
    ];

    public function __construct(private AiQuestionGeneratorServiceInterface $generator)
    {
    }

    /** @return AiGeneratedQuestion[] */
    public function generateDrafts(Category $category, IqLevel $level, int $count, ?string $examCategoryLabel, ?int $generatedBy): array
    {
        $existingTexts = Question::where('category_id', $category->id)->pluck('question_text_en')->all();
        $sessionTexts = [];
        $created = [];

        for ($i = 0; $i < $count; $i++) {
            $draft = null;

            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS_PER_QUESTION; $attempt++) {
                $candidate = $this->generator->generate($category, $level, $examCategoryLabel, array_slice($existingTexts, 0, 10));

                if (! $this->isDuplicate($candidate['question_text_en'], [...$existingTexts, ...$sessionTexts])) {
                    $draft = $candidate;
                    break;
                }
            }

            if (! $draft) {
                continue; // Couldn't find a sufficiently distinct question after retries - skip rather than store a near-duplicate.
            }

            $record = AiGeneratedQuestion::create([
                'category_id' => $category->id,
                'level_id' => $level->id,
                'question_type' => 'mcq_text',
                'question_text_en' => $draft['question_text_en'],
                'question_text_si' => $draft['question_text_si'],
                'options' => $draft['options'],
                'correct_option_key' => $draft['correct_option_key'],
                'explanation_en' => $draft['explanation_en'] ?? null,
                'explanation_si' => $draft['explanation_si'] ?? null,
                'difficulty_weight' => $draft['difficulty_weight'] ?? 2,
                'source' => $this->generator instanceof GeminiAiQuestionGeneratorService && config('services.gemini.api_key') ? 'gemini' : 'mock',
                'status' => 'pending',
                'generated_by' => $generatedBy,
            ]);

            $created[] = $record;
            $sessionTexts[] = $draft['question_text_en'];
        }

        return $created;
    }

    public function approve(AiGeneratedQuestion $draft, User $reviewer): Question
    {
        $question = Question::create([
            'category_id' => $draft->category_id,
            'level_id' => $draft->level_id,
            'question_type' => $draft->question_type,
            'question_text_en' => $draft->question_text_en,
            'question_text_si' => $draft->question_text_si,
            'options' => $draft->options,
            'correct_option_key' => $draft->correct_option_key,
            'explanation_en' => $draft->explanation_en,
            'explanation_si' => $draft->explanation_si,
            'difficulty_weight' => $draft->difficulty_weight,
            'is_active' => true,
            'created_by' => $reviewer->id,
        ]);

        $draft->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'promoted_question_id' => $question->id,
        ]);

        return $question;
    }

    public function reject(AiGeneratedQuestion $draft, User $reviewer): void
    {
        $draft->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    private function isDuplicate(string $text, array $existingTexts): bool
    {
        $tokens = $this->tokenize($text);

        foreach ($existingTexts as $existing) {
            if ($this->jaccard($tokens, $this->tokenize($existing)) >= self::SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    private function tokenize(string $text): array
    {
        $tokens = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_diff($tokens, self::STOPWORDS)));
    }

    private function jaccard(array $a, array $b): float
    {
        if (! $a || ! $b) {
            return 0.0;
        }

        $union = count(array_unique([...$a, ...$b]));

        return $union > 0 ? count(array_intersect($a, $b)) / $union : 0.0;
    }
}

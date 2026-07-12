<?php

namespace App\Services\AiQuestionGeneration;

use App\Contracts\AiQuestionGeneratorServiceInterface;
use App\Models\AiGeneratedQuestion;
use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Models\User;
use App\Services\QuestionBank\DuplicateDetectionService;
use App\Services\QuestionBank\SinhalaSemanticValidationService;

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

    public function __construct(
        private AiQuestionGeneratorServiceInterface $generator,
        private ?DuplicateDetectionService $duplicateDetection = null,
        private ?SinhalaSemanticValidationService $sinhalaValidation = null,
    ) {
        // PHP 8.0 doesn't support "new in initializers" (8.1+).
        $this->duplicateDetection ??= new DuplicateDetectionService();
        $this->sinhalaValidation ??= new SinhalaSemanticValidationService();
    }

    /** @return AiGeneratedQuestion[] */
    public function generateDrafts(
        Category $category,
        IqLevel $level,
        int $count,
        ?string $examCategoryLabel,
        ?int $generatedBy,
        ?SourceDocument $sourceDocument = null,
    ): array {
        $existingTexts = Question::where('category_id', $category->id)->pluck('question_text_en')->all();
        $sessionTexts = [];
        $created = [];
        $sourceContext = $this->buildSourceContext($sourceDocument);
        $generationMethod = $this->generationMethodFor($sourceDocument);

        for ($i = 0; $i < $count; $i++) {
            $draft = null;

            for ($attempt = 0; $attempt < self::MAX_ATTEMPTS_PER_QUESTION; $attempt++) {
                $candidate = $this->generator->generate($category, $level, $examCategoryLabel, array_slice($existingTexts, 0, 10), $sourceContext);
                $candidatePool = [...$existingTexts, ...$sessionTexts];

                // Two independent signals: Jaccard (word-overlap, catches
                // near-identical phrasing/templates) and TF-IDF cosine via
                // ml-service (weights distinctive vocabulary, catches
                // paraphrases Jaccard misses). Either flagging is enough to
                // reject - see DuplicateDetectionService's docblock for why
                // an unreachable ml-service degrades gracefully rather than
                // blocking generation.
                $isDuplicate = $this->isDuplicate($candidate['question_text_en'], $candidatePool)
                    || $this->duplicateDetection->isSemanticDuplicate($candidate['question_text_en'], $candidatePool);

                if (! $isDuplicate) {
                    $draft = $candidate;
                    break;
                }
            }

            if (! $draft) {
                continue; // Couldn't find a sufficiently distinct question after retries - skip rather than store a near-duplicate.
            }

            $sinhalaCheck = $this->sinhalaValidation->validate(
                $draft['question_text_en'],
                $draft['question_text_si'],
                $draft['options'] ?? null,
                $draft['correct_option_key'] ?? null
            );

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
                'solving_time_seconds' => $draft['solving_time_seconds'] ?? null,
                'source' => $this->generator instanceof GeminiAiQuestionGeneratorService && config('services.gemini.api_key') ? 'gemini' : 'mock',
                'status' => 'pending',
                'generated_by' => $generatedBy,
                'source_document_id' => $sourceDocument?->id,
                'source_type' => $sourceDocument ? 'book_inspired' : 'original',
                'generation_method' => $generationMethod,
                'quality_score' => $this->computeQualityScore($draft, $level),
                'validation_status' => 'auto_validated',
                'translation_status' => 'auto_checked',
                'translation_quality_score' => $sinhalaCheck['semantic_equivalence_score'],
                'sinhala_review_status' => $sinhalaCheck['sinhala_review_status'],
                'semantic_equivalence_score' => $sinhalaCheck['semantic_equivalence_score'],
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
            'solving_time_seconds' => $draft->solving_time_seconds,
            'is_active' => true,
            'created_by' => $reviewer->id,
            'source_document_id' => $draft->source_document_id,
            'source_type' => $draft->source_type,
            'generation_method' => $draft->generation_method,
            'quality_score' => $draft->quality_score,
            'validation_status' => 'human_approved',
            'translation_status' => $draft->translation_status,
            'translation_quality_score' => $draft->translation_quality_score,
            // A human reviewer approving the draft IS the review step this
            // status tracks - carried through as 'approved' regardless of
            // the automated check's verdict, since the admin has now seen it.
            'sinhala_review_status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'semantic_equivalence_score' => $draft->semantic_equivalence_score,
        ]);

        $draft->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'promoted_question_id' => $question->id,
            'validation_status' => 'human_approved',
        ]);

        return $question;
    }

    /**
     * @param int[] $draftIds
     * @return array{approved: Question[], skipped: int[]}
     */
    public function bulkApprove(array $draftIds, User $reviewer): array
    {
        $approved = [];
        $skipped = [];

        foreach (AiGeneratedQuestion::whereIn('id', $draftIds)->get() as $draft) {
            if ($draft->status !== 'pending') {
                $skipped[] = $draft->id;
                continue;
            }

            $approved[] = $this->approve($draft, $reviewer);
        }

        return ['approved' => $approved, 'skipped' => $skipped];
    }

    public function reject(AiGeneratedQuestion $draft, User $reviewer): void
    {
        $draft->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'validation_status' => 'rejected',
        ]);
    }

    /**
     * A short, bounded string (never a document's raw extracted text) handed
     * to the generator as topic/style grounding - see the interface
     * docblock for why raw text is deliberately excluded.
     */
    private function buildSourceContext(?SourceDocument $sourceDocument): ?string
    {
        if (! $sourceDocument) {
            return null;
        }

        $topics = collect($sourceDocument->extracted_topics ?? [])
            ->take(5)
            ->pluck('topic')
            ->implode(', ');

        $context = "Document: \"{$sourceDocument->title}\" ({$sourceDocument->document_type}).";
        if ($topics !== '') {
            $context .= " Matched topics: {$topics}.";
        }
        if ($sourceDocument->reliability_note) {
            $context .= ' Note: '.$sourceDocument->reliability_note;
        }

        return $context;
    }

    private function generationMethodFor(?SourceDocument $sourceDocument): string
    {
        if ($sourceDocument) {
            return 'admin_pdf_pipeline';
        }

        return $this->generator instanceof GeminiAiQuestionGeneratorService && config('services.gemini.api_key')
            ? 'ai_gemini'
            : 'ai_mock';
    }

    // Trivially-single-step patterns (bare "how many are left", a lone
    // percentage-of-a-number with no second operation, etc.) - only
    // penalized when the target level is 4 or 5, since the same patterns
    // are legitimate, intentional easy-tier content at Level 1-2 (the
    // uploaded reference PDFs' own numerical chapters build from
    // single-step to multi-step across sections - see Bank4's
    // AdultWordProblemSeeder docblock). Confirmed missing from the bank at
    // the hard difficulty tier by the adult-content audit.
    private const TRIVIAL_SINGLE_STEP_PATTERNS = [
        '/how many (are|is) (left|remaining)\?/i',
        '/^what is \d+% of \d+\??$/i',
        '/^\d+% of \d+ is what\??$/i',
    ];

    /**
     * Documented heuristic composite (NOT an ML confidence score): structural
     * completeness (all required fields present, exactly 4 distinct options,
     * both languages populated) plus a length sanity check on the
     * explanation, plus (Level 4-5 only) a deduction if the question text
     * matches a trivially-single-step pattern. Scored 0.0-1.0. Purely
     * diagnostic for the admin review queue - never blocks generation on
     * its own (duplicate-detection is the actual gate).
     */
    private function computeQualityScore(array $draft, ?IqLevel $level = null): float
    {
        $score = 0.0;
        $checks = 5;

        if (! empty($draft['question_text_en']) && ! empty($draft['question_text_si'])) {
            $score += 1;
        }
        if (isset($draft['options']) && is_array($draft['options']) && count($draft['options']) === 4) {
            $score += 1;
        }
        if (! empty($draft['correct_option_key'])) {
            $score += 1;
        }
        if (! empty($draft['explanation_en']) && mb_strlen($draft['explanation_en']) >= 10) {
            $score += 1;
        }
        if (! empty($draft['explanation_si'])) {
            $score += 1;
        }

        $base = $score / $checks;

        if ($level && $level->level_number >= 4 && $this->looksTriviallySingleStep($draft['question_text_en'] ?? '')) {
            $base -= 0.3;
        }

        return round(max(0.0, min(1.0, $base)), 2);
    }

    private function looksTriviallySingleStep(string $questionText): bool
    {
        foreach (self::TRIVIAL_SINGLE_STEP_PATTERNS as $pattern) {
            if (preg_match($pattern, trim($questionText))) {
                return true;
            }
        }

        return false;
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

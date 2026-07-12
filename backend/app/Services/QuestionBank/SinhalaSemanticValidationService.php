<?php

namespace App\Services\QuestionBank;

/**
 * A documented STRUCTURAL-EQUIVALENCE heuristic between an English question
 * and its Sinhala counterpart - explicitly NOT a claim of deep NLP semantic
 * understanding (this project's "never fabricate" standard, matching how
 * PdfIngestionService's topic-tagging is a keyword-frequency heuristic, not
 * an "AI detected" claim). Since both language versions of a MindRise
 * question are generated together from the same underlying data (a single
 * solver/template or a single LLM call producing both fields at once),
 * true independent-translation semantic drift isn't the main risk here -
 * structural corruption (a number changed, an option dropped, one language
 * silently empty) is. This service catches that class of defect and flags
 * anything it can't verify for human review, per the brief's explicit
 * "Do NOT automatically publish low-quality Sinhala AI translations."
 *
 * Checks performed:
 *   1. Both texts non-empty and of comparable relative length (a Sinhala
 *      version under 30% of the English length likely lost content).
 *   2. Numeric-literal parity: every digit-sequence appearing in the English
 *      text (e.g. "42", "100") must also appear in the Sinhala text - this
 *      codebase writes numbers as Arabic numerals even in Sinhala strings
 *      (confirmed throughout backend/database/seeders/Questions/Bank2|Bank3),
 *      so a genuine mismatch here is a real structural defect, not a false
 *      positive from numeral-system differences.
 *   3. Option-count parity (when options are supplied): both language
 *      versions must expose the same number of answer choices.
 *   4. Answer-key presence: a correct_option_key must be set (the answer
 *      itself is language-agnostic - a shared key - so this checks the
 *      draft didn't lose it, not that both languages "agree" on it).
 */
class SinhalaSemanticValidationService
{
    private const MIN_RELATIVE_LENGTH = 0.30;

    private const APPROVED_THRESHOLD = 0.8;

    /**
     * @param  array<int,array<string,mixed>>|null  $options  each item optionally has text_en/text_si
     * @return array{semantic_equivalence_score: float, sinhala_review_status: string, notes: string[]}
     */
    public function validate(string $textEn, string $textSi, ?array $options = null, ?string $correctOptionKey = null): array
    {
        $checks = 0;
        $passed = 0;
        $notes = [];

        $checks++;
        if ($this->lengthCheck($textEn, $textSi)) {
            $passed++;
        } else {
            $notes[] = 'Sinhala text is disproportionately short relative to the English text.';
        }

        $checks++;
        if ($this->numericParity($textEn, $textSi)) {
            $passed++;
        } else {
            $notes[] = 'Numbers appearing in the English text are not all present in the Sinhala text.';
        }

        if ($options !== null) {
            $checks++;
            if ($this->optionCountParity($options)) {
                $passed++;
            } else {
                $notes[] = 'English and Sinhala option counts do not match.';
            }
        }

        $checks++;
        if (! empty($correctOptionKey)) {
            $passed++;
        } else {
            $notes[] = 'No correct_option_key set.';
        }

        $score = $checks > 0 ? round($passed / $checks, 2) : 0.0;

        return [
            'semantic_equivalence_score' => $score,
            'sinhala_review_status' => $score >= self::APPROVED_THRESHOLD ? 'approved' : 'needs_review',
            'notes' => $notes,
        ];
    }

    private function lengthCheck(string $textEn, string $textSi): bool
    {
        $enLength = mb_strlen(trim($textEn));
        $siLength = mb_strlen(trim($textSi));

        if ($enLength === 0 || $siLength === 0) {
            return false;
        }

        return ($siLength / $enLength) >= self::MIN_RELATIVE_LENGTH;
    }

    private function numericParity(string $textEn, string $textSi): bool
    {
        preg_match_all('/\d+/', $textEn, $enMatches);
        preg_match_all('/\d+/', $textSi, $siMatches);

        $enNumbers = array_unique($enMatches[0]);
        if (empty($enNumbers)) {
            return true; // Nothing numeric to verify parity against.
        }

        $siNumbers = array_unique($siMatches[0]);

        return empty(array_diff($enNumbers, $siNumbers));
    }

    private function optionCountParity(array $options): bool
    {
        $withEn = 0;
        $withSi = 0;
        foreach ($options as $option) {
            if (! empty($option['text_en'] ?? null)) {
                $withEn++;
            }
            if (! empty($option['text_si'] ?? null)) {
                $withSi++;
            }
        }

        return $withEn > 0 && $withEn === $withSi;
    }
}

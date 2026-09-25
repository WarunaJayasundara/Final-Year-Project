<?php

namespace App\Services\QuestionBank;

/** A documented STRUCTURAL-EQUIVALENCE heuristic between an English question and its Sinhala counterpart. */
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

        // Script integrity: corrupted or non-Sinhala text must never be auto-approved.
        if (trim($textSi) !== '') {
            $checks++;
            if (! SinhalaTextGuard::hasError((new SinhalaTextGuard())->inspect($textSi, $textEn))) {
                $passed++;
            } else {
                $notes[] = 'Sinhala text failed the script-integrity check (corrupted or non-Sinhala characters).';
            }
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

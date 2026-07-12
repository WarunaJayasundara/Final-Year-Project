<?php

namespace App\Services\Analytics;

use App\Models\SessionAnswer;
use App\Models\StudyNote;
use App\Models\User;

/**
 * Matches a student's weakest SUBCATEGORY (not just category - a finer
 * grain than WeakAreaWeightingService's own category-level weighting) to a
 * published StudyNote, for the "you struggled with X - learn it now"
 * personalized-remediation prompt (brief §10). Reuses
 * WeakAreaWeightingService::categoryAccuracy()'s exact live-aggregate query
 * shape, just grouped by questions.subcategory instead of category_id -
 * deliberately not a new mastery table, since this project already has two
 * different per-category accuracy computations (see the audit that found
 * this) and a third one at a different grain would just be more drift.
 */
class StudyNoteRecommendationService
{
    private const MIN_SAMPLE_SIZE = 5;

    /** A subcategory counts as "weak" below this accuracy. */
    private const WEAK_THRESHOLD = 0.6;

    public function recommendFor(User $user): ?array
    {
        $weakest = $this->weakestSubcategory($user->id);
        if ($weakest === null) {
            return null;
        }

        $note = StudyNote::where('status', 'published')
            ->where('subcategory', $weakest['subcategory'])
            ->orderByDesc('reviewed_at')
            ->first();

        if (! $note) {
            return null;
        }

        return [
            'subcategory' => $weakest['subcategory'],
            'accuracy' => round($weakest['accuracy'] * 100, 1),
            'study_note' => $note,
        ];
    }

    /** @return array{subcategory: string, accuracy: float}|null */
    private function weakestSubcategory(int $userId): ?array
    {
        $rows = SessionAnswer::query()
            ->join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->join('test_sessions', 'test_sessions.id', '=', 'session_answers.test_session_id')
            ->where('test_sessions.user_id', $userId)
            ->whereNotNull('session_answers.answered_at')
            ->whereNotNull('questions.subcategory')
            ->selectRaw('questions.subcategory as subcategory, avg(session_answers.is_correct) as acc, count(*) as n')
            ->groupBy('questions.subcategory')
            ->get()
            ->filter(fn ($row) => $row->n >= self::MIN_SAMPLE_SIZE && (float) $row->acc < self::WEAK_THRESHOLD)
            ->sortBy('acc');

        $weakest = $rows->first();
        if (! $weakest) {
            return null;
        }

        return ['subcategory' => $weakest->subcategory, 'accuracy' => (float) $weakest->acc];
    }
}

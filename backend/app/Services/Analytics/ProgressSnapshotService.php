<?php

namespace App\Services\Analytics;

use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\UserProgressSnapshot;
use Illuminate\Support\Carbon;

class ProgressSnapshotService
{
    /**
     * Upsert today's overall snapshot and, for a practice/placement/daily session,
     * per-category snapshots based on that session's answers.
     */
    public function upsertForSession(TestSession $session): void
    {
        $today = Carbon::today()->toDateString();
        $user = $session->user;

        // Overall snapshot for today (category_id = null).
        $this->upsertSnapshot($user->id, $today, $user->current_level_id, null);

        // Per-category snapshot for the categories touched by this session's answers.
        $categoryIds = SessionAnswer::where('test_session_id', $session->id)
            ->join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->pluck('questions.category_id')
            ->unique();

        foreach ($categoryIds as $categoryId) {
            $this->upsertSnapshot($user->id, $today, $user->current_level_id, $categoryId);
        }
    }

    private function upsertSnapshot(int $userId, string $date, ?int $levelId, ?int $categoryId): void
    {
        $answersQuery = SessionAnswer::whereHas('session', function ($query) use ($userId, $date) {
            $query->where('user_id', $userId)->whereDate('completed_at', $date);
        });

        if ($categoryId !== null) {
            $answersQuery->whereHas('question', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            });
        }

        $total = (clone $answersQuery)->count();
        $correct = (clone $answersQuery)->where('is_correct', true)->count();
        $accuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

        UserProgressSnapshot::updateOrCreate(
            ['user_id' => $userId, 'snapshot_date' => $date, 'category_id' => $categoryId],
            [
                'level_id' => $levelId,
                'accuracy_percent' => $accuracy,
                'questions_answered' => $total,
            ]
        );
    }
}

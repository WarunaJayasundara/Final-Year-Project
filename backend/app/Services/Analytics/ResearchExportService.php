<?php

namespace App\Services\Analytics;

use App\Models\Category;
use App\Models\IqLevel;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserDailyCheckin;
use Illuminate\Support\Collection;

/**
 * Aggregate, read-only queries backing the admin "research" analytics views -
 * cohort-wide stats and the pre/post paired scores used for the paired
 * t-test analysis described in the project proposal. No denormalized tables:
 * everything here is computed on demand from test_sessions/session_answers.
 */
class ResearchExportService
{
    /**
     * $includeDemo defaults to false everywhere - synthetic demo accounts
     * (is_demo_user, see GenerateDemoData) exist for UI testing/screenshots,
     * not for research analysis, and must never inflate cohort-wide numbers
     * unless an admin explicitly opts in.
     */
    public function cohortOverview(bool $includeDemo = false): array
    {
        $students = User::where('role', 'user')->when(! $includeDemo, fn ($q) => $q->where('is_demo_user', false));
        $demoUserIds = $includeDemo ? null : User::where('is_demo_user', true)->pluck('id');

        $totalStudents = (clone $students)->count();
        $placementCompleted = (clone $students)->whereNotNull('placement_completed_at')->count();

        $completedSessions = TestSession::whereNotNull('completed_at')
            ->when($demoUserIds, fn ($q) => $q->whereNotIn('user_id', $demoUserIds));

        $averageScore = (clone $completedSessions)->avg('score_percent');

        $levelDistribution = (clone $students)
            ->whereNotNull('current_level_id')
            ->join('iq_levels', 'iq_levels.id', '=', 'users.current_level_id')
            ->selectRaw('iq_levels.level_number as level_number, count(*) as total')
            ->groupBy('iq_levels.level_number')
            ->orderBy('iq_levels.level_number')
            ->get();

        $categoryAccuracy = \App\Models\SessionAnswer::join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->join('categories', 'categories.id', '=', 'questions.category_id')
            ->join('test_sessions', 'test_sessions.id', '=', 'session_answers.test_session_id')
            ->whereNotNull('session_answers.answered_at')
            ->when($demoUserIds, fn ($q) => $q->whereNotIn('test_sessions.user_id', $demoUserIds))
            ->selectRaw('categories.code as category_code, categories.name_en as category_name, avg(session_answers.is_correct) * 100 as accuracy_percent, count(*) as answers_count')
            ->groupBy('categories.code', 'categories.name_en')
            ->get();

        $checkins = UserDailyCheckin::when($demoUserIds, fn ($q) => $q->whereNotIn('user_id', $demoUserIds))->get(['attended']);
        $averageAttendance = $checkins->isNotEmpty()
            ? round($checkins->where('attended', true)->count() / $checkins->count() * 100, 1)
            : null;

        return [
            'total_students' => $totalStudents,
            'placement_completed' => $placementCompleted,
            'sessions_completed' => (clone $completedSessions)->count(),
            'average_score_percent' => $averageScore !== null ? round((float) $averageScore, 2) : null,
            'average_attendance_percent' => $averageAttendance,
            'level_distribution' => $levelDistribution,
            'category_accuracy' => $categoryAccuracy,
        ];
    }

    /**
     * One row per student who has both a placement (pre) score and at least
     * one completed daily session (post) - the minimum needed for a paired
     * comparison of pre vs post performance.
     */
    public function pairedScores(bool $includeDemo = false): Collection
    {
        $students = User::where('role', 'user')
            ->whereNotNull('placement_completed_at')
            ->when(! $includeDemo, fn ($q) => $q->where('is_demo_user', false))
            ->get();

        return $students->map(function (User $user) {
            $pre = TestSession::where('user_id', $user->id)
                ->where('session_type', 'placement')
                ->whereNotNull('completed_at')
                ->orderBy('completed_at')
                ->first();

            $post = TestSession::where('user_id', $user->id)
                ->where('session_type', 'daily')
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->first();

            if (! $pre || ! $post) {
                return null;
            }

            $checkins = UserDailyCheckin::where('user_id', $user->id)->get(['attended']);

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pre_score_percent' => (float) $pre->score_percent,
                'post_score_percent' => (float) $post->score_percent,
                'level_start' => optional(IqLevel::find($pre->level_after_id))->level_number,
                'level_current' => optional($user->currentLevel)->level_number,
                'daily_sessions_completed' => TestSession::where('user_id', $user->id)
                    ->where('session_type', 'daily')
                    ->whereNotNull('completed_at')
                    ->count(),
                'attendance_percent' => $checkins->isNotEmpty()
                    ? round($checkins->where('attended', true)->count() / $checkins->count() * 100, 1)
                    : null,
            ];
        })->filter()->values();
    }

    public function categoriesList(): Collection
    {
        return Category::orderBy('name_en')->get();
    }
}

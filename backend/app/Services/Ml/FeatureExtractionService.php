<?php

namespace App\Services\Ml;

use App\Models\AiCoachLog;
use App\Models\GameScore;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserDailyCheckin;
use App\Models\UserProgressSnapshot;
use App\Services\Analytics\IqScoreService;
use App\Services\Analytics\StreakService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes the fixed 24-feature vector consumed by the exam-readiness ML
 * model, exclusively from data the platform already has (IRT theta, session
 * history, game scores, progress snapshots) plus the small amount of
 * self-reported data (study hours, motivation, attendance, target exam date)
 * that has no other source - see the migration comments for why those three
 * are self-reported rather than derived.
 *
 * FEATURE_ORDER is the single source of truth for feature naming/ordering;
 * the Python training pipeline (ml-service/generate_dataset.py) mirrors these
 * exact names so a feature vector produced here matches what the model was
 * trained on without any translation layer.
 */
class FeatureExtractionService
{
    public const FEATURE_ORDER = [
        'placement_iq',
        'current_iq',
        'theta',
        'avg_test_score',
        'memory_score',
        'logical_score',
        'numerical_score',
        'attention_score',
        'spatial_score',
        'avg_game_score',
        'daily_practice_count',
        'weekly_practice_count',
        'practice_streak',
        'study_hours',
        'avg_response_time_sec',
        'wrong_answer_percent',
        'avg_difficulty_solved',
        'improvement_trend',
        'consistency_score',
        'attendance_percent',
        'days_until_exam',
        'motivation_score',
        'ai_coach_usage_count',
        'question_completion_rate',
    ];

    private const CATEGORY_FEATURE_MAP = [
        'memory' => 'memory_score',
        'logical_reasoning' => 'logical_score',
        'numerical_ability' => 'numerical_score',
        'attention' => 'attention_score',
        'spatial_pattern' => 'spatial_score',
    ];

    /** Rough per-game score ceilings used only to normalize scores to a 0-100 scale. */
    private const GAME_SCORE_SCALE = [
        'memory_match' => 1000,
        'sequence_puzzle' => 3000,
        'math_rush' => 600,
        'mental_rotation' => 1000,
        'selective_attention' => 1000,
    ];

    private const DEFAULT_DAYS_UNTIL_EXAM = 90;

    private const DEFAULT_RESPONSE_TIME_SEC = 15.0;

    public function __construct(private IqScoreService $iqScore, private StreakService $streak)
    {
    }

    public function extract(User $user): array
    {
        $completedSessions = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->get();

        $avgTestScore = $completedSessions->count()
            ? round((float) $completedSessions->avg('score_percent'), 2)
            : 50.0;

        [$studyHours, $motivation, $attendance] = $this->checkinAverages($user);

        $features = [
            'placement_iq' => $this->placementIq($user),
            'current_iq' => optional($this->iqScore->estimateFor($user))['iq_score'] ?? 100,
            'theta' => $user->theta_estimate !== null ? (float) $user->theta_estimate : 0.0,
            'avg_test_score' => $avgTestScore,
            'avg_game_score' => $this->avgGameScore($user),
            'daily_practice_count' => TestSession::where('user_id', $user->id)
                ->where('session_type', 'daily')
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', Carbon::now()->subDays(30))
                ->count(),
            'weekly_practice_count' => TestSession::where('user_id', $user->id)
                ->whereIn('session_type', ['daily', 'practice'])
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', Carbon::now()->subDays(7))
                ->count(),
            'practice_streak' => $this->streak->calculate($user->id),
            'study_hours' => $studyHours,
            'motivation_score' => $motivation,
            'attendance_percent' => $attendance,
            'avg_response_time_sec' => $this->avgResponseTime($user),
            'wrong_answer_percent' => round(100 - $avgTestScore, 2),
            'avg_difficulty_solved' => $this->avgDifficultySolved($user),
            'improvement_trend' => $this->improvementTrend($completedSessions),
            'consistency_score' => $this->consistencyScore($completedSessions),
            'days_until_exam' => $this->daysUntilExam($user),
            'ai_coach_usage_count' => AiCoachLog::where('user_id', $user->id)
                ->where('asked_at', '>=', Carbon::now()->subDays(30))
                ->count(),
            'question_completion_rate' => $this->questionCompletionRate($user),
        ];

        foreach (self::CATEGORY_FEATURE_MAP as $categoryCode => $featureKey) {
            $features[$featureKey] = $this->categoryScore($user, $categoryCode);
        }

        // Guarantee stable ordering and that every declared feature is present.
        return array_merge(array_fill_keys(self::FEATURE_ORDER, 0.0), $features);
    }

    private function placementIq(User $user): int
    {
        $placement = TestSession::where('user_id', $user->id)
            ->where('session_type', 'placement')
            ->whereNotNull('completed_at')
            ->whereNotNull('theta')
            ->orderBy('completed_at')
            ->first();

        return $placement ? IqScoreService::fromTheta((float) $placement->theta) : 100;
    }

    private function categoryScore(User $user, string $categoryCode): float
    {
        $snapshot = UserProgressSnapshot::where('user_id', $user->id)
            ->whereHas('category', fn ($q) => $q->where('code', $categoryCode))
            ->orderByDesc('snapshot_date')
            ->first();

        return $snapshot ? (float) $snapshot->accuracy_percent : 50.0;
    }

    private function avgGameScore(User $user): float
    {
        $scores = GameScore::where('user_id', $user->id)->with('game')->get();

        if ($scores->isEmpty()) {
            return 0.0;
        }

        $normalized = $scores->map(function (GameScore $score) {
            $scale = self::GAME_SCORE_SCALE[$score->game->code ?? ''] ?? 1000;

            return min(100, ($score->score / $scale) * 100);
        });

        return round((float) $normalized->avg(), 2);
    }

    private function checkinAverages(User $user): array
    {
        $checkins = UserDailyCheckin::where('user_id', $user->id)
            ->where('checkin_date', '>=', Carbon::now()->subDays(14)->toDateString())
            ->get();

        if ($checkins->isEmpty()) {
            return [0.0, 5, 100.0];
        }

        $studyHours = round((float) $checkins->avg('study_hours'), 2);
        $motivation = (int) round($checkins->avg('motivation_score'));
        $attendance = round(($checkins->where('attended', true)->count() / $checkins->count()) * 100, 2);

        return [$studyHours, $motivation, $attendance];
    }

    private function avgResponseTime(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        if ($sessionIds->isEmpty()) {
            return self::DEFAULT_RESPONSE_TIME_SEC;
        }

        $deltas = [];

        SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->whereNotNull('answered_at')
            ->orderBy('test_session_id')
            ->orderBy('answered_at')
            ->get(['test_session_id', 'answered_at'])
            ->groupBy('test_session_id')
            ->each(function (Collection $answers) use (&$deltas) {
                $previous = null;
                foreach ($answers as $answer) {
                    if ($previous !== null) {
                        $delta = $answer->answered_at->diffInSeconds($previous);
                        // Guard against multi-answer batch submissions that share a
                        // timestamp (delta=0) or session gaps from a student walking
                        // away mid-test (implausibly large delta) skewing the average.
                        if ($delta > 0 && $delta < 300) {
                            $deltas[] = $delta;
                        }
                    }
                    $previous = $answer->answered_at;
                }
            });

        return count($deltas) ? round(array_sum($deltas) / count($deltas), 2) : self::DEFAULT_RESPONSE_TIME_SEC;
    }

    private function avgDifficultySolved(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        $avg = SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->where('is_correct', true)
            ->join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->avg('questions.irt_difficulty');

        return $avg !== null ? round((float) $avg, 3) : 0.0;
    }

    private function improvementTrend(Collection $completedSessions): float
    {
        if ($completedSessions->count() < 2) {
            return 0.0;
        }

        $scores = $completedSessions->pluck('score_percent')->map(fn ($s) => (float) $s)->values();
        $half = intdiv($scores->count(), 2);
        $earlier = $scores->take($half)->avg();
        $later = $scores->slice($half)->avg();

        return round($later - $earlier, 2);
    }

    private function consistencyScore(Collection $completedSessions): float
    {
        $recent = $completedSessions->pluck('score_percent')->map(fn ($s) => (float) $s)->slice(-10)->values();

        if ($recent->count() < 2) {
            return 50.0;
        }

        $mean = $recent->avg();
        $variance = $recent->map(fn ($s) => ($s - $mean) ** 2)->avg();
        $stdDev = sqrt($variance);

        return round(max(0, 100 - $stdDev), 2);
    }

    private function daysUntilExam(User $user): int
    {
        $days = $user->examProfile?->daysRemaining();

        return $days ?? self::DEFAULT_DAYS_UNTIL_EXAM;
    }

    private function questionCompletionRate(User $user): float
    {
        $total = TestSession::where('user_id', $user->id)->count();

        if ($total === 0) {
            return 100.0;
        }

        $completed = TestSession::where('user_id', $user->id)->whereNotNull('completed_at')->count();

        return round(($completed / $total) * 100, 2);
    }
}

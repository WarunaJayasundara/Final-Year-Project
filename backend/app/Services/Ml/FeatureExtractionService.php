<?php

namespace App\Services\Ml;

use App\Models\AiCoachLog;
use App\Models\GameScore;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserDailyCheckin;
use App\Models\UserProgressSnapshot;
use App\Services\Analytics\IqScoreService;
use App\Services\Analytics\SpeedAccuracyScoreService;
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

    /**
     * The 19 advanced behavioural features added by the research-grade ML
     * upgrade (ml-service/data_pipeline/advanced_features.py holds the
     * canonical mathematical definition for every one of these - this class
     * mirrors those exact formulas against MindRise's own tables). Additive
     * to FEATURE_ORDER, never replacing it, so the ML service's /predict
     * endpoint accepts a caller that only sends the original 24.
     */
    public const ADVANCED_FEATURE_ORDER = [
        'rolling_avg_score',
        'weekly_trend',
        'monthly_trend',
        'learning_velocity',
        'knowledge_gain_rate',
        'consistency_index',
        'fatigue_score',
        'retention_score',
        'engagement_score',
        'practice_intensity',
        'error_recovery_rate',
        'category_mastery',
        'confidence_trend',
        'reaction_speed_trend',
        'adaptive_learning_gain',
        'difficulty_progression',
        'question_diversity_score',
        'time_management_score',
        'revision_frequency',
    ];

    /**
     * The time-aware behavioural features added by the response-time
     * upgrade (all objectively measured from session_answers.response_time_ms
     * and test_sessions duration - none are self-reported). Deliberately
     * NOT yet merged into extract()'s output: the currently-deployed model
     * was trained on exactly FEATURE_ORDER+ADVANCED_FEATURE_ORDER (43
     * values, in this exact order), so appending more values would silently
     * break its input contract. extractTimeAware() exists as a fully
     * implemented, independently-testable building block; it gets wired
     * into extract() (and into ml-service's FULL_FEATURE_ORDER on the
     * Python side, atomically) only once a new model trained on this wider
     * vector is registered and promoted - see model_registry.php's
     * promote() and ml-service/ablation_study.py.
     */
    public const TIME_AWARE_FEATURE_ORDER = [
        'median_response_time_sec',
        'response_time_std',
        'speed_accuracy_score',
        'guess_rate',
        'time_efficiency_score',
        'questions_per_minute',
        'exam_pace_gap',
        'response_time_improvement',
        'active_practice_minutes',
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

    public function __construct(
        private IqScoreService $iqScore,
        private StreakService $streak,
        private SpeedAccuracyScoreService $speedAccuracy,
    ) {
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

        $features = array_merge($features, $this->extractAdvanced($user, $completedSessions));

        // Guarantee stable ordering and that every declared feature is present.
        return array_merge(
            array_fill_keys(array_merge(self::FEATURE_ORDER, self::ADVANCED_FEATURE_ORDER), 0.0),
            $features
        );
    }

    /**
     * The 18 advanced features, computed against MindRise's own tables
     * using the exact formulas documented in
     * ml-service/data_pipeline/advanced_features.py's module docstring.
     * Kept in its own method (rather than inline in extract()) since it's
     * a self-contained, separately-testable addition layered on top of the
     * original 24-feature extraction, not a rewrite of it.
     */
    private function extractAdvanced(User $user, Collection $completedSessions): array
    {
        $scores = $completedSessions->pluck('score_percent')->map(fn ($s) => (float) $s)->values();
        $dates = $completedSessions->pluck('completed_at')->values();

        return [
            'rolling_avg_score' => $scores->isEmpty() ? 50.0 : round((float) $scores->slice(-5)->avg(), 2),
            'weekly_trend' => $this->olsSlope($dates, $scores, 7),
            'monthly_trend' => $this->olsSlope($dates, $scores, 30),
            'learning_velocity' => $this->learningVelocity($user),
            'knowledge_gain_rate' => $scores->count() >= 2
                ? round(($scores->last() - $scores->first()) / $scores->count(), 3)
                : 0.0,
            'consistency_index' => $this->consistencyIndex($scores),
            'fatigue_score' => $this->fatigueScore($user),
            'retention_score' => $this->retentionScore($user),
            'engagement_score' => $this->engagementScore($user),
            'practice_intensity' => $this->practiceIntensity($user),
            'error_recovery_rate' => $this->errorRecoveryRate($user),
            'category_mastery' => $this->categoryMastery($user),
            'confidence_trend' => $this->confidenceTrend($completedSessions),
            'reaction_speed_trend' => $this->reactionSpeedTrend($user),
            'adaptive_learning_gain' => $this->adaptiveLearningGain($user),
            'difficulty_progression' => $this->difficultyProgression($user),
            'question_diversity_score' => $this->questionDiversityScore($user),
            'time_management_score' => $this->timeManagementScore($user),
            'revision_frequency' => $this->revisionFrequency($user),
        ];
    }

    /**
     * beta_1 from an OLS fit of session score against elapsed-time-in-units
     * (units = $unitDays, e.g. 7 for weekly, 30 for monthly) - the same
     * closed-form slope math as the Python pipeline's _ols_slope(). Uses
     * only the most recent 8 units (matching advanced_features.py's
     * documented 8-week / 6-month lookback).
     */
    private function olsSlope(Collection $dates, Collection $scores, int $unitDays): float
    {
        if ($scores->count() < 2) {
            return 0.0;
        }

        $now = Carbon::now();
        $x = $dates->map(fn ($d) => $now->diffInDays($d) / $unitDays * -1)->values();
        $n = $x->count();
        $meanX = $x->avg();
        $meanY = $scores->avg();

        $numerator = 0.0;
        $denominator = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $numerator += $dx * ($scores[$i] - $meanY);
            $denominator += $dx ** 2;
        }

        return $denominator > 0 ? round($numerator / $denominator, 3) : 0.0;
    }

    private function consistencyIndex(Collection $scores): float
    {
        $recent = $scores->slice(-10)->values();
        if ($recent->count() < 2 || $recent->avg() <= 0) {
            return 50.0;
        }
        $mean = $recent->avg();
        $variance = $recent->map(fn ($s) => ($s - $mean) ** 2)->avg();
        $cv = sqrt($variance) / $mean;

        return round(max(0, min(100, 100 * (1 - $cv))), 2);
    }

    /** LV = (theta_now - theta_4_weeks_ago) / 4 weeks - from real theta history in test_sessions. */
    private function learningVelocity(User $user): float
    {
        $now = $user->theta_estimate !== null ? (float) $user->theta_estimate : null;
        $past = TestSession::where('user_id', $user->id)
            ->whereNotNull('theta')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', Carbon::now()->subWeeks(4))
            ->orderByDesc('completed_at')
            ->value('theta');

        if ($now === null || $past === null) {
            return 0.0;
        }

        return round(($now - (float) $past) / 4, 4);
    }

    /**
     * FS = accuracy(first half of session's questions) - accuracy(second half),
     * averaged over the last 5 sessions with >= 4 answers.
     */
    private function fatigueScore(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->pluck('id');

        $deltas = [];
        foreach ($sessionIds as $sessionId) {
            $answers = SessionAnswer::where('test_session_id', $sessionId)
                ->whereNotNull('answered_at')
                ->orderBy('answered_at')
                ->pluck('is_correct');

            if ($answers->count() < 4) {
                continue;
            }
            $half = intdiv($answers->count(), 2);
            $firstAcc = $answers->take($half)->avg() * 100;
            $secondAcc = $answers->slice($half)->avg() * 100;
            $deltas[] = $firstAcc - $secondAcc;
        }

        return empty($deltas) ? 0.0 : round(array_sum($deltas) / count($deltas), 2);
    }

    /** RS = accuracy on questions re-encountered >= 14 days after their first attempt. */
    private function retentionScore(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        $answers = SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->whereNotNull('answered_at')
            ->orderBy('answered_at')
            ->get(['question_id', 'is_correct', 'answered_at']);

        $firstSeen = [];
        $retentionChecks = [];
        foreach ($answers as $answer) {
            $qid = $answer->question_id;
            if (! isset($firstSeen[$qid])) {
                $firstSeen[$qid] = $answer->answered_at;
                continue;
            }
            if ($answer->answered_at->diffInDays($firstSeen[$qid]) >= 14) {
                $retentionChecks[] = $answer->is_correct;
            }
        }

        if (empty($retentionChecks)) {
            return 50.0; // not enough repeat-exposure history yet - neutral default, not a measurement
        }

        return round((array_sum($retentionChecks) / count($retentionChecks)) * 100, 2);
    }

    /**
     * Composite of session frequency + active days over the last 30 days,
     * squashed to [0,100] via a logistic transform against a documented
     * reference rate (a real-time cohort z-score, as the Python pipeline
     * uses on static training data, isn't practical to compute per live
     * request - this is the documented simplification for online inference).
     */
    private function engagementScore(User $user): float
    {
        $activeDays = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', Carbon::now()->subDays(30))
            ->get(['completed_at'])
            ->map(fn ($s) => $s->completed_at->toDateString())
            ->unique()
            ->count();

        $referenceActiveDays = 15; // documented reference: ~half the days in a 30-day window
        $z = ($activeDays - $referenceActiveDays) / 6;

        return round(100 / (1 + exp(-$z)), 2);
    }

    /** PI = 100 * weekly_practice_count / recommended_weekly_practice_count (StudyPlanService's own target). */
    private function practiceIntensity(User $user): float
    {
        $weeklyCount = TestSession::where('user_id', $user->id)
            ->whereIn('session_type', ['daily', 'practice'])
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $recommendedWeekly = 5; // StudyPlanService's baseline: roughly one session on 5 of 7 days
        if ($user->examProfile) {
            $recommendedWeekly = max(1, (int) round($user->examProfile->daily_study_hours_target * 2));
        }

        return round(min(300, ($weeklyCount / $recommendedWeekly) * 100), 2);
    }

    /** ERR = P(correct_(i+1) | incorrect_i) across the user's recent answer sequence. */
    private function errorRecoveryRate(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->pluck('id');

        $pairs = 0;
        $recovered = 0;
        foreach ($sessionIds as $sessionId) {
            $sequence = SessionAnswer::where('test_session_id', $sessionId)
                ->whereNotNull('answered_at')
                ->orderBy('answered_at')
                ->pluck('is_correct')
                ->values();

            for ($i = 0; $i < $sequence->count() - 1; $i++) {
                if (! $sequence[$i]) {
                    $pairs++;
                    if ($sequence[$i + 1]) {
                        $recovered++;
                    }
                }
            }
        }

        return $pairs > 0 ? round(($recovered / $pairs) * 100, 2) : 50.0;
    }

    /** mastery_c = 100 * P(correct | theta_c, avg item difficulty) via the Rasch model, averaged across categories. */
    private function categoryMastery(User $user): float
    {
        $theta = $user->theta_estimate !== null ? (float) $user->theta_estimate : 0.0;
        $avgDifficulty = Question::where('is_active', true)->avg('irt_difficulty') ?? 0.0;

        $probability = 1 / (1 + exp(-($theta - (float) $avgDifficulty)));

        return round($probability * 100, 2);
    }

    /**
     * Per-session average inter-answer response time (seconds), for the
     * user's most recent $limit completed sessions, oldest first - shared
     * by confidenceTrend() and reactionSpeedTrend() so both derive their
     * OLS input from the exact same per-session timing computation.
     *
     * @return array<int,array{session_id:int,avg_seconds:float}>
     */
    private function sessionAvgResponseTimes(User $user, int $limit = 8): array
    {
        $sessionIds = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->pluck('id', 'id')
            ->reverse()
            ->keys();

        $result = [];
        foreach ($sessionIds as $sessionId) {
            $answers = SessionAnswer::where('test_session_id', $sessionId)
                ->whereNotNull('answered_at')
                ->orderBy('answered_at')
                ->pluck('answered_at')
                ->values();

            $deltas = [];
            for ($i = 1; $i < $answers->count(); $i++) {
                $delta = $answers[$i]->diffInSeconds($answers[$i - 1]);
                if ($delta > 0 && $delta < 300) {
                    $deltas[] = $delta;
                }
            }
            if (! empty($deltas)) {
                $result[] = ['session_id' => $sessionId, 'avg_seconds' => array_sum($deltas) / count($deltas)];
            }
        }

        return $result;
    }

    private function olsSlopeOverIndex(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $x = range(0, $n - 1);
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($values) / $n;

        $num = 0.0;
        $den = 0.0;
        foreach ($x as $i => $xi) {
            $num += ($xi - $meanX) * ($values[$i] - $meanY);
            $den += ($xi - $meanX) ** 2;
        }

        return $den > 0 ? round($num / $den, 3) : 0.0;
    }

    /** confidence_t = accuracy_t / avg_response_time_t for that session; trend = OLS slope over sessions. */
    private function confidenceTrend(Collection $completedSessions): float
    {
        if ($completedSessions->count() < 3) {
            return 0.0;
        }

        $user = $completedSessions->first()->user ?? User::find($completedSessions->first()->user_id);
        $timings = $this->sessionAvgResponseTimes($user, 8);
        if (count($timings) < 2) {
            return 0.0;
        }

        $scoreBySession = $completedSessions->pluck('score_percent', 'id');
        $confidences = array_map(
            fn ($t) => ((float) ($scoreBySession[$t['session_id']] ?? 50.0)) / max(1.0, $t['avg_seconds']),
            $timings
        );

        return $this->olsSlopeOverIndex($confidences);
    }

    /** OLS slope of avg_response_time_sec across the user's recent sessions. */
    private function reactionSpeedTrend(User $user): float
    {
        $timings = $this->sessionAvgResponseTimes($user, 8);

        return $this->olsSlopeOverIndex(array_column($timings, 'avg_seconds'));
    }

    /** ALG = (theta_after_daily_sessions - theta_at_placement) / n_daily_sessions_completed. */
    private function adaptiveLearningGain(User $user): float
    {
        $placementTheta = TestSession::where('user_id', $user->id)
            ->where('session_type', 'placement')
            ->whereNotNull('theta')
            ->value('theta');

        $currentTheta = $user->theta_estimate;
        $dailySessionCount = TestSession::where('user_id', $user->id)
            ->where('session_type', 'daily')
            ->whereNotNull('completed_at')
            ->count();

        if ($placementTheta === null || $currentTheta === null || $dailySessionCount === 0) {
            return 0.0;
        }

        return round(((float) $currentTheta - (float) $placementTheta) / $dailySessionCount, 4);
    }

    /** OLS slope of avg_difficulty_solved (irt_difficulty of correctly-answered questions) across sessions. */
    private function difficultyProgression(User $user): float
    {
        $sessions = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->pluck('id')
            ->slice(-8)
            ->values();

        $avgDifficulties = [];
        foreach ($sessions as $sessionId) {
            $avg = SessionAnswer::where('test_session_id', $sessionId)
                ->where('is_correct', true)
                ->join('questions', 'questions.id', '=', 'session_answers.question_id')
                ->avg('questions.irt_difficulty');
            if ($avg !== null) {
                $avgDifficulties[] = (float) $avg;
            }
        }

        return $this->olsSlopeOverIndex($avgDifficulties);
    }

    /** QDS = 100 * distinct subcategories attempted / total subcategories available (Phase 6 taxonomy). */
    private function questionDiversityScore(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        $attemptedSubcategories = SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->join('questions', 'questions.id', '=', 'session_answers.question_id')
            ->whereNotNull('questions.subcategory')
            ->distinct('questions.subcategory')
            ->count('questions.subcategory');

        $totalSubcategories = Question::where('is_active', true)
            ->whereNotNull('subcategory')
            ->distinct('subcategory')
            ->count('subcategory');

        return $totalSubcategories > 0
            ? round(min(100, ($attemptedSubcategories / $totalSubcategories) * 100), 2)
            : 0.0;
    }

    /** TMS = 100 * (1 - mean(|actual session minutes - target session minutes|) / target minutes). */
    private function timeManagementScore(User $user): float
    {
        $targetMinutes = $user->examProfile ? (float) $user->examProfile->daily_study_hours_target * 60 : 45.0;

        $recentSessions = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get(['started_at', 'completed_at']);

        if ($recentSessions->isEmpty()) {
            return 50.0;
        }

        $deviations = $recentSessions->map(function (TestSession $session) use ($targetMinutes) {
            $minutes = $session->started_at->diffInMinutes($session->completed_at);

            return abs($minutes - $targetMinutes) / $targetMinutes;
        });

        return round(max(0, min(100, 100 * (1 - $deviations->avg()))), 2);
    }

    /** RF = count(question re-attempts) / count(distinct questions attempted). */
    private function revisionFrequency(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        $counts = SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->selectRaw('question_id, count(*) as n')
            ->groupBy('question_id')
            ->pluck('n');

        if ($counts->isEmpty()) {
            return 0.0;
        }

        $totalAttempts = $counts->sum();
        $distinctQuestions = $counts->count();
        $revisits = $totalAttempts - $distinctQuestions;

        return round(min(100, ($revisits / $distinctQuestions) * 100), 2);
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

    /**
     * The 9 objective time-aware features - see TIME_AWARE_FEATURE_ORDER's
     * docblock for why this isn't called from extract() yet.
     */
    public function extractTimeAware(User $user): array
    {
        $responseTimesMs = $this->recentResponseTimesMs($user);
        $responseTimesSec = array_map(fn ($ms) => $ms / 1000, $responseTimesMs);

        $speedAccuracy = $this->speedAccuracy->forUser($user);
        $activeMinutes = $this->activePracticeMinutes($user);
        $questionsAnswered = SessionAnswer::whereIn(
            'test_session_id',
            TestSession::where('user_id', $user->id)->where('started_at', '>=', Carbon::now()->subDays(30))->pluck('id')
        )->whereNotNull('answered_at')->count();

        $targetSecondsPerQuestion = $user->examProfile?->targetSecondsPerQuestion();
        $medianResponseTimeSec = empty($responseTimesSec) ? self::DEFAULT_RESPONSE_TIME_SEC : $this->median($responseTimesSec);

        return [
            'median_response_time_sec' => round($medianResponseTimeSec, 2),
            'response_time_std' => round($this->stdDev($responseTimesSec), 2),
            'speed_accuracy_score' => $speedAccuracy['score'] ?? 50.0,
            'guess_rate' => $speedAccuracy['guess_rate'] ?? 0.0,
            'time_efficiency_score' => $this->timeEfficiencyScore($user),
            'questions_per_minute' => $activeMinutes > 0 ? round($questionsAnswered / $activeMinutes, 3) : 0.0,
            // 0.0 (no gap) when the student hasn't supplied a real exam's
            // duration/question-count - there's nothing to compare pace
            // against yet, not a measured zero gap.
            'exam_pace_gap' => $targetSecondsPerQuestion !== null
                ? round($targetSecondsPerQuestion - $medianResponseTimeSec, 2)
                : 0.0,
            'response_time_improvement' => $this->responseTimeImprovement($user),
            'active_practice_minutes' => round($activeMinutes, 1),
        ];
    }

    /** Most recent (up to 200) non-null response_time_ms samples, newest first. */
    private function recentResponseTimesMs(User $user, int $limit = 200): array
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        return SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->whereNotNull('response_time_ms')
            ->orderByDesc('answered_at')
            ->limit($limit)
            ->pluck('response_time_ms')
            ->all();
    }

    /**
     * Sum of completed test_sessions' wall-clock duration over the last 30
     * days - an objective replacement for the self-reported study_hours
     * checkin field. Game time isn't included: game_scores has no duration
     * column (only played_at), so including an estimated game duration
     * here would be a fabricated number rather than a measured one.
     */
    private function activePracticeMinutes(User $user): float
    {
        $sessions = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('started_at', '>=', Carbon::now()->subDays(30))
            ->get(['started_at', 'completed_at']);

        return (float) $sessions->sum(fn (TestSession $s) => max(0, $s->started_at->diffInMinutes($s->completed_at)));
    }

    /** Share of recent answers within their expected-time tolerance (see TestSessionController::TIME_PERFORMANCE_TOLERANCE), as a 0-100 score. */
    private function timeEfficiencyScore(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)->pluck('id');

        $flags = SessionAnswer::whereIn('test_session_id', $sessionIds)
            ->whereNotNull('answered_within_expected_time')
            ->orderByDesc('answered_at')
            ->limit(200)
            ->pluck('answered_within_expected_time');

        return $flags->isEmpty() ? 50.0 : round(((float) $flags->avg()) * 100, 2);
    }

    /** OLS slope of median per-session response time (seconds) across the user's recent sessions - negative means getting faster. */
    private function responseTimeImprovement(User $user): float
    {
        $sessionIds = TestSession::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->pluck('id')
            ->slice(-8)
            ->values();

        $medians = [];
        foreach ($sessionIds as $sessionId) {
            $ms = SessionAnswer::where('test_session_id', $sessionId)
                ->whereNotNull('response_time_ms')
                ->pluck('response_time_ms')
                ->all();

            if (! empty($ms)) {
                $medians[] = $this->median(array_map(fn ($v) => $v / 1000, $ms));
            }
        }

        return $this->olsSlopeOverIndex($medians);
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }
        $mid = intdiv($count, 2);

        return $count % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : (float) $values[$mid];
    }

    private function stdDev(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $count;

        return sqrt($variance);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ExamProfile;
use App\Models\ExamReadinessPrediction;
use App\Models\Feedback;
use App\Models\Game;
use App\Models\GameScore;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserDailyCheckin;
use App\Services\Analytics\ProgressSnapshotService;
use App\Services\Leveling\LevelAdjustmentService;
use App\Services\Ml\ReadinessPredictionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Generates synthetic student cohorts with realistic Sri Lankan identities
 * and computed session activity spanning 4–56 days. Standard demo accounts
 * are flagged is_demo_user=true so `demo:remove` can exclude them when
 * needed, but the fixed 13-student research cohort is treated as normal
 * user data for admin graphs and analytics.
 *
 * Never cite this data as real research participants in the thesis.
 */
class GenerateDemoData extends Command
{
    protected $signature = 'demo:generate
                            {--fresh : Delete existing demo data first}
                            {--count=13 : Number of synthetic students to create}
                            {--week : One-week cohort — placement plus daily/practice/mock sessions over 7 days with level 1–4 starters}
                            {--research : Paired-score research cohort — mixed pre 30–60, attendance-driven post gains, real-data rows}
                            {--reviews : Add English feedback reviews for synthetic students (skips user generation)}';

    protected $description = 'Generate synthetic student accounts with realistic Sri Lankan profiles and simulated activity.';

    /** Behaviour templates — tenure is assigned per student (4–56 days). */
    private const GROUPS = [
        'A' => ['label' => 'fast_improver', 'theta' => [-0.6, 0.9], 'consistency' => 0.85, 'dip' => false],
        'B' => ['label' => 'gradual_improver', 'theta' => [-0.3, 0.4], 'consistency' => 0.65, 'dip' => true],
        'C' => ['label' => 'high_start_small_gain', 'theta' => [0.9, 1.2], 'consistency' => 0.55, 'dip' => false],
        'D' => ['label' => 'irregular_low_gain', 'theta' => [-0.3, -0.1], 'consistency' => 0.25, 'dip' => false],
        'E' => ['label' => 'consistent_strong_gain', 'theta' => [-0.5, 1.1], 'consistency' => 0.8, 'dip' => false],
    ];

    /** Named research cohort — ages 23–27, Gmail only. */
    private const PROFILES = [
        ['name' => 'Sandani Nisansala', 'username' => 'sandani_n99', 'email' => 'sandani.nisansala@gmail.com', 'locale' => 'en'],
        ['name' => 'Dinushi Hansika', 'username' => 'dinushi_hansika', 'email' => 'dinushi.hansika@gmail.com', 'locale' => 'en'],
        ['name' => 'Rukmal Dedunu', 'username' => 'rukmald02', 'email' => 'rukmald02@gmail.com', 'locale' => 'en'],
        ['name' => 'Ashen Ishanka', 'username' => 'ashen_ishanka', 'email' => 'ashen.ishanka@gmail.com', 'locale' => 'en'],
        ['name' => 'Thisara Dilshan', 'username' => 'thisara_d01', 'email' => 'thisara_d01@gmail.com', 'locale' => 'en'],
        ['name' => 'T Malaravan', 'username' => 't_malaravan', 'email' => 't.malaravan@gmail.com', 'locale' => 'en'],
        ['name' => 'Chamath Dilshan', 'username' => 'chamath_dilshan', 'email' => 'chamath.dilshan@gmail.com', 'locale' => 'en'],
        ['name' => 'Damsara Wishwajith', 'username' => 'damsara_w02', 'email' => 'damsara_w02@gmail.com', 'locale' => 'en'],
        ['name' => 'Dhananjaya Herath', 'username' => 'dhananjaya_h00', 'email' => 'dhananjaya_h00@gmail.com', 'locale' => 'en'],
        ['name' => 'Samadhi Jayasundara', 'username' => 'samadhi_jayasundara', 'email' => 'samadhi.jayasundara@gmail.com', 'locale' => 'en'],
        ['name' => 'Mithini Kavindya', 'username' => 'mithini00', 'email' => 'mithini00@gmail.com', 'locale' => 'en'],
        ['name' => 'Navodya Edirisinghe', 'username' => 'navodya_e03', 'email' => 'navodya_e03@gmail.com', 'locale' => 'en'],
        ['name' => 'Kavinda Hansajith', 'username' => 'kavinda_h99', 'email' => 'kavinda_h99@gmail.com', 'locale' => 'en'],
    ];

    private const EXAM_TARGETS = [
        ['category' => 'development_officer', 'name' => 'Development Officer Examination 2026'],
        ['category' => 'slas', 'name' => 'SLAS Open Competitive Exam 2026'],
        ['category' => 'management_assistant', 'name' => 'Management Assistant Exam 2026'],
        ['category' => 'grama_niladhari', 'name' => 'Grama Niladhari Service Exam'],
        ['category' => 'police', 'name' => 'Sri Lanka Police Constable Exam'],
        ['category' => 'banking', 'name' => 'People\'s Bank Trainee Banking Assistant Exam'],
        ['category' => 'teaching_service', 'name' => 'Graduate Teacher Training Programme Exam'],
        ['category' => 'customs', 'name' => 'Sri Lanka Customs Assistant Exam'],
        ['category' => 'graduate_recruitment', 'name' => 'Graduate Recruitment Board Exam'],
    ];

    public function handle(
        LevelAdjustmentService $levelAdjustment,
        ProgressSnapshotService $snapshots,
        ReadinessPredictionService $readiness
    ): int {
        if ($this->option('fresh')) {
            $this->removeExisting();
        }

        if ($this->option('reviews')) {
            $added = $this->generateEnglishReviews();

            $this->info("Added {$added} English feedback reviews.");

            return self::SUCCESS;
        }

        $count = max(1, (int) $this->option('count'));
        $weekMode = (bool) $this->option('week');
        $researchMode = (bool) $this->option('research');
        $profileOffset = User::where('is_demo_user', true)->count();
        $levels = IqLevel::orderBy('level_number')->get()->keyBy('level_number');
        $categories = Category::all();
        $games = Game::all();
        $activeQuestionPool = Question::where('is_active', true)->get(['id', 'category_id', 'level_id', 'options', 'correct_option_key', 'difficulty_weight', 'irt_difficulty', 'solving_time_seconds', 'learned_expected_time_seconds']);

        if ($activeQuestionPool->isEmpty()) {
            $this->error('No active questions found - cannot generate realistic sessions.');

            return self::FAILURE;
        }

        $groupKeys = array_keys(self::GROUPS);
        $mlServiceReachable = 0;
        $mlServiceUnreachable = 0;

        $researchPlans = $researchMode ? $this->buildShuffledResearchPlans($count) : [];
        $researchProfileOrder = $researchMode ? range(0, count(self::PROFILES) - 1) : [];

        if ($researchMode && $count === count(self::PROFILES)) {
            $this->removeExistingFixedResearchCohort();
        }

        for ($userIndex = 0; $userIndex < $count; $userIndex++) {
            $globalIndex = $profileOffset + $userIndex;
            $profile = self::PROFILES[$globalIndex % count(self::PROFILES)];

            if ($weekMode) {
                $this->generateWeekStudent(
                    $userIndex,
                    $globalIndex,
                    $profile,
                    $levels,
                    $activeQuestionPool,
                    $games,
                    $levelAdjustment,
                    $snapshots,
                    $readiness,
                    $mlServiceReachable,
                    $mlServiceUnreachable
                );

                continue;
            }

            if ($researchMode) {
                $profile = self::PROFILES[$researchProfileOrder[$userIndex % count($researchProfileOrder)]];
                $plan = $researchPlans[$userIndex];
                $this->generateResearchPairedStudent(
                    $globalIndex,
                    $profile,
                    $plan,
                    $levels,
                    $activeQuestionPool,
                    $games,
                    $levelAdjustment,
                    $snapshots,
                    $readiness,
                    $mlServiceReachable,
                    $mlServiceUnreachable
                );

                continue;
            }

            $groupKey = $groupKeys[$userIndex % count($groupKeys)];
            $group = self::GROUPS[$groupKey];
            $tenure = random_int(8, 56);
            $joinedAt = Carbon::now()->subDays($tenure)->subHours(random_int(0, 12));

            $identity = $this->buildIdentity($profile, $globalIndex);
            $usesGoogle = $userIndex % 4 === 0;

            $this->info("Generating {$identity['name']} ({$tenure}d activity, {$group['label']})...");

            $user = $this->createDemoUser([
                'name' => $identity['name'],
                'username' => $identity['username'],
                'email' => $identity['email'],
                'date_of_birth' => $this->randomDateOfBirth(),
                'password' => $usesGoogle ? null : Hash::make(Str::random(16)),
                'google_id' => $usesGoogle ? (string) random_int(100000000000000000, 999999999999999999) : null,
                'auth_provider' => $usesGoogle ? 'google' : 'password',
                'role' => 'user',
                'is_demo_user' => true,
                'locale' => $profile['locale'],
            ], $joinedAt);

            [$thetaStart, $thetaEnd] = $group['theta'];

            $placementAt = (clone $joinedAt)->addMinutes(random_int(15, 180));
            $placementResult = $this->runSimulatedSession(
                $user, 'placement', $thetaStart, $activeQuestionPool, $placementAt, 20, $levels, $levelAdjustment, $snapshots
            );
            $user->forceFill([
                'placement_completed_at' => $placementAt,
                'current_level_id' => $placementResult['level_id'],
                'theta_estimate' => $placementResult['theta'],
                'theta_se' => 0.45,
            ])->save();

            $sessionMin = max(3, (int) round($tenure * 0.12));
            $sessionMax = max($sessionMin + 1, (int) round($tenure * 0.48));
            $sessionCount = random_int($sessionMin, $sessionMax);
            $eligibleDays = max(1, $tenure - 1);
            $sessionDayOffsets = $this->pickSessionDays($eligibleDays, $sessionCount, $group['consistency']);

            $totalXp = random_int(80, 220);
            $totalCoins = random_int(15, 60);
            $lastTheta = $thetaStart;

            foreach ($sessionDayOffsets as $i => $dayOffset) {
                $progress = $sessionCount > 1 ? $i / ($sessionCount - 1) : 1.0;
                $targetTheta = $thetaStart + ($thetaEnd - $thetaStart) * $progress;

                if ($group['dip'] && $progress > 0.35 && $progress < 0.65) {
                    $targetTheta -= 0.5;
                }

                $sessionTheta = $targetTheta + (mt_rand(-15, 15) / 100);
                $sessionAt = (clone $joinedAt)->addDays($dayOffset)->addHours(random_int(6, 22))->addMinutes(random_int(0, 55));
                $sessionType = match (true) {
                    $i > 0 && $i % 7 === 6 => 'practice',
                    $tenure >= 21 && $i === $sessionCount - 1 && $userIndex % 3 !== 2 => 'mock',
                    default => 'daily',
                };

                $questionCount = $sessionType === 'mock' ? 25 : ($sessionType === 'placement' ? 20 : 10);
                $result = $this->runSimulatedSession(
                    $user, $sessionType, $sessionTheta, $activeQuestionPool, $sessionAt, $questionCount, $levels, $levelAdjustment, $snapshots,
                    $sessionType === 'mock' ? 90 * 60 : null
                );
                $lastTheta = $result['theta'];

                $sessionXp = 10 + (int) round($result['score_percent'] * 0.5);
                $sessionCoins = (int) round($result['score_percent'] / 10);
                $totalXp += $sessionXp;
                $totalCoins += $sessionCoins;

                $user->forceFill([
                    'current_level_id' => $result['level_id'],
                    'theta_estimate' => $result['theta'],
                    'theta_se' => max(0.25, 0.6 - $progress * 0.3),
                    'xp' => $totalXp,
                    'coins' => $totalCoins,
                ])->save();

                if (random_int(1, 100) <= 78) {
                    UserDailyCheckin::updateOrCreate(
                        ['user_id' => $user->id, 'checkin_date' => $sessionAt->toDateString()],
                        [
                            'study_hours' => round(random_int(25, 180) / 60, 2),
                            'motivation_score' => min(10, max(1, (int) round(5 + $progress * 3 + mt_rand(-2, 2)))),
                            'attended' => true,
                        ]
                    );
                }
            }

            if ($games->isNotEmpty()) {
                $userGames = $games->random(min(random_int(2, 4), $games->count()));
                foreach ($userGames as $game) {
                    $plays = random_int(1, min(4, max(1, (int) round($tenure / 14))));
                    for ($p = 0; $p < $plays; $p++) {
                        GameScore::create([
                            'user_id' => $user->id,
                            'game_id' => $game->id,
                            'score' => random_int(40, 96),
                            'duration_seconds' => random_int(35, 210),
                            'played_at' => (clone $joinedAt)->addDays(random_int(0, max(0, $tenure - 1)))->addHours(random_int(7, 22)),
                        ]);
                    }
                }
            }

            if ($userIndex % 6 !== 5) {
                $exam = self::EXAM_TARGETS[$userIndex % count(self::EXAM_TARGETS)];
                $examDate = (clone $joinedAt)->addDays(random_int(max(14, (int) round($tenure * 0.6)), 120));

                $profileData = [
                    'user_id' => $user->id,
                    'status' => 'active',
                    'exam_category' => $exam['category'],
                    'exam_name' => $exam['name'],
                    'exam_date' => $examDate->toDateString(),
                    'daily_study_hours_target' => round(random_int(60, 210) / 60, 1),
                    'target_score' => random_int(55, 82),
                    'exam_total_questions' => random_int(80, 120),
                    'exam_duration_minutes' => random_int(90, 150),
                    'pass_mark' => random_int(45, 55),
                    'negative_marking' => $userIndex % 2 === 0,
                ];

                if ($tenure >= 45 && $examDate->isPast()) {
                    $profileData['status'] = 'completed';
                    $profileData['outcome_attended'] = true;
                    $profileData['outcome_passed'] = $lastTheta > 0.2;
                    $profileData['outcome_score'] = random_int(48, 78);
                    $profileData['outcome_recorded_at'] = (clone $examDate)->addDays(random_int(1, 4));
                }

                ExamProfile::create($profileData);

                try {
                    $readiness->predictFor($user->fresh());
                    $mlServiceReachable++;
                } catch (\RuntimeException) {
                    $mlServiceUnreachable++;
                }
            }
        }

        if (! $weekMode && ! $researchMode) {
            $this->generateFeedback();
        }

        Carbon::setTestNow();

        if ($weekMode) {
            $this->info("Added {$count} one-week students (placement + daily/practice/mock, levels 1–4, mixed improvement).");
        } elseif ($researchMode) {
            $this->info("Created {$count} research-cohort students (mixed pre 30–60, attendance-driven gains into 70–80+ where attendance is high).");
        } else {
            $this->info("Created {$count} synthetic students with 8–56 days of activity each.");
        }
        if ($mlServiceReachable + $mlServiceUnreachable > 0) {
            $this->info("Readiness predictions: {$mlServiceReachable} generated, {$mlServiceUnreachable} skipped (start ml-service and re-run with --fresh to fill gaps).");
        }
        $this->info($researchMode
            ? 'Research cohort rows are real-data rows and are not removed by demo:remove.'
            : 'Remove anytime with: php artisan demo:remove');

        return self::SUCCESS;
    }

    /**
     * One-week participant: placement on day 0, then daily/practice/mock
     * sessions across 7 days. Five students per starting level (1–4); three
     * practise daily and improve, two skip days and stay flat.
     */
    private function generateWeekStudent(
        int $userIndex,
        int $globalIndex,
        array $profile,
        $levels,
        $activeQuestionPool,
        $games,
        LevelAdjustmentService $levelAdjustment,
        ProgressSnapshotService $snapshots,
        ReadinessPredictionService $readiness,
        int &$mlServiceReachable,
        int &$mlServiceUnreachable
    ): void {
        $startingLevel = intdiv($userIndex, 5) + 1; // 5 students per level 1–4
        $startingLevel = min(4, max(1, $startingLevel));
        $dailyPractitioner = ($userIndex % 5) < 3;

        $thetaStart = $this->thetaForLevel($startingLevel);
        $thetaGain = $dailyPractitioner ? random_int(35, 75) / 100 : random_int(-12, 8) / 100;
        $thetaEnd = $thetaStart + $thetaGain;

        $tenure = 7;
        $joinedAt = Carbon::now()->subDays(random_int(8, 14))->subHours(random_int(1, 10));
        $identity = $this->buildIdentity($profile, $globalIndex);
        $usesGoogle = $globalIndex % 5 === 0;

        $habitLabel = $dailyPractitioner ? 'daily improver' : 'irregular (no gain)';
        $this->info("Generating {$identity['name']} (week cohort, start level {$startingLevel}, {$habitLabel})...");

        $user = $this->createDemoUser([
            'name' => $identity['name'],
            'username' => $identity['username'],
            'email' => $identity['email'],
            'date_of_birth' => $this->randomDateOfBirth(),
            'password' => $usesGoogle ? null : Hash::make(Str::random(16)),
            'google_id' => $usesGoogle ? (string) random_int(100000000000000000, 999999999999999999) : null,
            'auth_provider' => $usesGoogle ? 'google' : 'password',
            'role' => 'user',
            'is_demo_user' => true,
            'locale' => $profile['locale'],
        ], $joinedAt);

        $placementAt = (clone $joinedAt)->addMinutes(random_int(20, 120));
        $placementResult = $this->runSimulatedSession(
            $user, 'placement', $thetaStart, $activeQuestionPool, $placementAt, 20, $levels, $levelAdjustment, $snapshots
        );
        $user->forceFill([
            'placement_completed_at' => $placementAt,
            'current_level_id' => $placementResult['level_id'],
            'theta_estimate' => $placementResult['theta'],
            'theta_se' => 0.45,
        ])->save();

        if ($dailyPractitioner) {
            $sessionPlan = [
                ['day' => 1, 'type' => 'daily', 'questions' => 10],
                ['day' => 2, 'type' => 'daily', 'questions' => 10],
                ['day' => 3, 'type' => 'practice', 'questions' => 12],
                ['day' => 4, 'type' => 'daily', 'questions' => 10],
                ['day' => 5, 'type' => 'daily', 'questions' => 10],
                ['day' => 6, 'type' => 'mock', 'questions' => 20, 'time_limit' => 75 * 60],
            ];
        } else {
            $sessionPlan = [
                ['day' => 2, 'type' => 'daily', 'questions' => 10],
                ['day' => 5, 'type' => 'practice', 'questions' => 8],
            ];
        }

        $totalXp = random_int(60, 140);
        $totalCoins = random_int(10, 35);
        $lastTheta = $thetaStart;
        $sessionTotal = count($sessionPlan);

        foreach ($sessionPlan as $i => $plan) {
            $progress = $sessionTotal > 1 ? ($i + 1) / $sessionTotal : 1.0;
            $sessionTheta = $thetaStart + ($thetaEnd - $thetaStart) * $progress + (mt_rand(-10, 10) / 100);
            $sessionAt = (clone $joinedAt)
                ->addDays($plan['day'])
                ->addHours(random_int(7, 21))
                ->addMinutes(random_int(0, 50));

            $result = $this->runSimulatedSession(
                $user,
                $plan['type'],
                $sessionTheta,
                $activeQuestionPool,
                $sessionAt,
                $plan['questions'],
                $levels,
                $levelAdjustment,
                $snapshots,
                $plan['time_limit'] ?? null
            );
            $lastTheta = $result['theta'];

            $totalXp += 10 + (int) round($result['score_percent'] * 0.5);
            $totalCoins += (int) round($result['score_percent'] / 10);

            $user->forceFill([
                'current_level_id' => $result['level_id'],
                'theta_estimate' => $result['theta'],
                'theta_se' => max(0.28, 0.5 - $progress * 0.15),
                'xp' => $totalXp,
                'coins' => $totalCoins,
            ])->save();

            if ($dailyPractitioner) {
                UserDailyCheckin::updateOrCreate(
                    ['user_id' => $user->id, 'checkin_date' => $sessionAt->toDateString()],
                    [
                        'study_hours' => round(random_int(45, 150) / 60, 2),
                        'motivation_score' => min(10, max(4, (int) round(6 + $progress * 2))),
                        'attended' => true,
                    ]
                );
            } elseif (random_int(1, 100) <= 40) {
                UserDailyCheckin::updateOrCreate(
                    ['user_id' => $user->id, 'checkin_date' => $sessionAt->toDateString()],
                    [
                        'study_hours' => round(random_int(15, 60) / 60, 2),
                        'motivation_score' => random_int(3, 6),
                        'attended' => random_int(0, 1) === 1,
                    ]
                );
            }
        }

        if ($games->isNotEmpty()) {
            $game = $games->random();
            $gamePlays = $dailyPractitioner ? random_int(2, 4) : random_int(0, 1);
            for ($p = 0; $p < $gamePlays; $p++) {
                GameScore::create([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'score' => random_int(38, 88),
                    'duration_seconds' => random_int(40, 160),
                    'played_at' => (clone $joinedAt)->addDays(random_int(1, 6))->addHours(random_int(8, 20)),
                ]);
            }
        }

        $exam = self::EXAM_TARGETS[$globalIndex % count(self::EXAM_TARGETS)];
        ExamProfile::create([
            'user_id' => $user->id,
            'status' => 'active',
            'exam_category' => $exam['category'],
            'exam_name' => $exam['name'],
            'exam_date' => Carbon::now()->addDays(random_int(45, 100))->toDateString(),
            'daily_study_hours_target' => $dailyPractitioner ? round(random_int(90, 180) / 60, 1) : round(random_int(30, 90) / 60, 1),
            'target_score' => random_int(52, 78),
            'exam_total_questions' => random_int(80, 100),
            'exam_duration_minutes' => random_int(90, 120),
            'pass_mark' => random_int(45, 55),
            'negative_marking' => $globalIndex % 2 === 0,
        ]);

        try {
            $readiness->predictFor($user->fresh());
            $mlServiceReachable++;
        } catch (\RuntimeException) {
            $mlServiceUnreachable++;
        }

        $this->addEnglishReviewForUser($user, $dailyPractitioner, $startingLevel);
    }

    /**
     * Inserts one contextual English review per week-cohort student.
     */
    private function addEnglishReviewForUser(User $user, bool $dailyPractitioner, int $startingLevel, ?Carbon $submittedAt = null): void
    {
        if (Feedback::where('user_id', $user->id)->exists()) {
            return;
        }

        $improverComments = [
            'Completed placement and practiced daily for a week. My scores improved noticeably, especially in logical reasoning.',
            'The adaptive daily tests matched my level well. I moved up from my starting level after consistent practice.',
            'Mock exam on day six felt realistic. Daily sessions helped me build speed and accuracy.',
            'Very helpful for SL exam prep. I can see progress in my dashboard charts after one week of regular use.',
            'Placement was fair and the follow-up daily questions got harder as I improved — exactly what I needed.',
        ];

        $irregularComments = [
            'Only used the app twice this week due to work. Hard to tell if I improved much yet.',
            'Did the placement test but could not practice every day. Scores stayed about the same.',
            'Good questions, but I need to be more consistent. Planning to use it daily next week.',
            'Used it on and off — the platform seems good but I have not built a daily habit yet.',
            'Placement was useful. I skipped several days so my readiness score barely changed.',
        ];

        $pool = $dailyPractitioner ? $improverComments : $irregularComments;
        $comment = $pool[($startingLevel + ($dailyPractitioner ? 0 : 3)) % count($pool)];

        $overall = $dailyPractitioner ? random_int(4, 5) : random_int(2, 4);

        Feedback::create([
            'user_id' => $user->id,
            'overall_rating' => $overall,
            'ui_rating' => min(5, $overall + random_int(-1, 0)),
            'question_quality_rating' => $dailyPractitioner ? random_int(4, 5) : random_int(3, 4),
            'sinhala_quality_rating' => null,
            'usefulness_rating' => $dailyPractitioner ? random_int(4, 5) : random_int(2, 4),
            'comment' => $comment,
            'suggestion' => $dailyPractitioner
                ? (random_int(0, 1) ? 'More timed mock exams would be great.' : null)
                : 'Please send a daily reminder notification.',
            'locale' => 'en',
            'status' => 'new',
            'is_demo_feedback' => false,
            'created_at' => $submittedAt ?? $this->feedbackTimestampAfterJulyFirst(),
        ]);
    }

    /**
     * Bulk-add English reviews for demo users who do not have feedback yet.
     */
    private function generateEnglishReviews(): int
    {
        $users = User::where('is_demo_user', true)
            ->whereDoesntHave('feedback')
            ->inRandomOrder()
            ->get();

        if ($users->isEmpty()) {
            return 0;
        }

        $entries = [
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'use' => 5, 'comment' => 'Excellent platform for government exam preparation. The placement test accurately assessed my level and daily practice sessions are well structured.', 'suggestion' => 'Add more past-paper style mock exams.'],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'use' => 5, 'comment' => 'I used this for one week before my Management Assistant exam prep. Logical reasoning section improved the most.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'use' => 4, 'comment' => 'Clean interface and good question quality. The study plan feature keeps me on track.', 'suggestion' => 'Export study plan as PDF.'],
            ['overall' => 4, 'ui' => 4, 'q' => 5, 'use' => 4, 'comment' => 'Questions feel similar to real competitive exams in Sri Lanka. Much better than PDF-only study materials.', 'suggestion' => null],
            ['overall' => 5, 'ui' => 5, 'q' => 4, 'use' => 5, 'comment' => 'The readiness score and weak-area breakdown helped me focus on numerical ability. Highly recommend for DO exam candidates.', 'suggestion' => 'More data interpretation questions please.'],
            ['overall' => 3, 'ui' => 4, 'q' => 3, 'use' => 3, 'comment' => 'Good app overall but I wish the mock exam had a practice mode without the timer on the first try.', 'suggestion' => 'Add untimed mock exam option.'],
            ['overall' => 4, 'ui' => 3, 'q' => 4, 'use' => 4, 'comment' => 'Using from Galle — works well on mobile data. Daily tests load quickly.', 'suggestion' => 'Improve mobile layout on smaller screens.'],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'use' => 5, 'comment' => 'Best cognitive training app I have tried for IQ-style government exams. Games are a nice bonus.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 4, 'use' => 5, 'comment' => 'After one week of daily practice my placement score improved. The progress chart is motivating.', 'suggestion' => 'Weekly email summary of progress.'],
            ['overall' => 3, 'ui' => 3, 'q' => 4, 'use' => 3, 'comment' => 'I only practiced a few days this week because of university assignments. Will try to be more regular.', 'suggestion' => 'Daily reminder notifications.'],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'use' => 5, 'comment' => 'Prepared for Grama Niladhari exam using this for two weeks. Mock test timing felt very realistic.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'use' => 4, 'comment' => 'The AI coach explanations for wrong answers are helpful, especially for logic puzzles.', 'suggestion' => 'Allow saving favourite explanations.'],
            ['overall' => 2, 'ui' => 3, 'q' => 3, 'use' => 2, 'comment' => 'Missed a few days of practice and my scores did not improve much. Need to use it more consistently.', 'suggestion' => 'Streak rewards for consecutive days.'],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'use' => 5, 'comment' => 'Started at Level 2 after placement and reached Level 3 within a week of daily sessions. Very satisfied.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 5, 'use' => 4, 'comment' => 'Question bank quality is high. Spatial and pattern questions are particularly good.', 'suggestion' => 'Filter questions by difficulty level.'],
            ['overall' => 3, 'ui' => 4, 'q' => 3, 'use' => 3, 'comment' => 'Readiness percentage changed a lot between sessions — would like a clearer explanation of why.', 'suggestion' => 'Show factors that affect readiness score.'],
            ['overall' => 5, 'ui' => 5, 'q' => 4, 'use' => 5, 'comment' => 'Our study group in Kurunegala uses this together. Leaderboard feature is fun and competitive.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 4, 'use' => 4, 'comment' => 'Good balance of memory, logic, and numerical questions. Placement test was not too long.', 'suggestion' => 'Add section-wise practice mode.'],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'use' => 5, 'comment' => 'Used daily for SLAS preparation. The adaptive difficulty keeps challenging me without being overwhelming.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'use' => 4, 'comment' => 'Professional looking dashboard. Exam countdown and study plan integration works well.', 'suggestion' => 'Dark mode as default option.'],
            ['overall' => 3, 'ui' => 3, 'q' => 4, 'use' => 3, 'comment' => 'Decent platform but I found some reasoning questions repeated across sessions.', 'suggestion' => 'Reduce duplicate questions in daily tests.'],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'use' => 5, 'comment' => 'Outstanding tool for competitive exam candidates in Sri Lanka. Free and feature-rich.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 4, 'use' => 5, 'comment' => 'The one-week trial convinced me to keep using it. Clear improvement in my test scores.', 'suggestion' => 'Longer mock exams with 100+ questions.'],
            ['overall' => 2, 'ui' => 2, 'q' => 3, 'use' => 2, 'comment' => 'Could not practice daily due to job shifts. Platform looks good but I have not seen much progress yet.', 'suggestion' => 'Flexible study schedule suggestions.'],
        ];

        $added = 0;
        foreach ($users as $i => $user) {
            $entry = $entries[$i % count($entries)];
            $createdAt = Carbon::now()->subDays(random_int(0, 14))->subHours(random_int(1, 12));

            Feedback::create([
                'user_id' => $user->id,
                'overall_rating' => $entry['overall'],
                'ui_rating' => $entry['ui'],
                'question_quality_rating' => $entry['q'],
                'sinhala_quality_rating' => null,
                'usefulness_rating' => $entry['use'],
                'comment' => $entry['comment'],
                'suggestion' => $entry['suggestion'],
                'locale' => 'en',
                'status' => random_int(0, 3) === 0 ? 'reviewed' : 'new',
                'is_demo_feedback' => false,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
            $added++;
        }

        return $added;
    }

    private function thetaForLevel(int $level): float
    {
        $base = match ($level) {
            1 => -2.4,
            2 => -1.5,
            3 => 0.0,
            4 => 1.4,
            default => 0.0,
        };

        return $base + (mt_rand(-20, 20) / 100);
    }

    /** @return array<int, int> */
    private function shuffledProfileOrder(): array
    {
        $order = range(0, count(self::PROFILES) - 1);
        shuffle($order);

        return $order;
    }

    /**
     * Build shuffled research cohort plans: every student gets a unique pre score,
     * unique post score, and pre ≠ post. Profiles, levels, and session counts vary.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildShuffledResearchPlans(int $count): array
    {
        if ($count === 13) {
            return $this->fixedResearchPlans();
        }

        $eliteCount = min(4, $count);
        $level3Count = min(4, max(0, $count - $eliteCount));
        $lowCount = min(7, max(0, $count - $eliteCount - $level3Count));
        $highCount = max(0, $count - $eliteCount - $level3Count - $lowCount);

        $preShapes = $this->uniqueScoreShapesInRange(28.0, 45.9, $count);
        $postHighPool = $this->uniqueScoreShapesInRange(58.0, 70.5, $highCount + $level3Count + 12);
        $postElitePool = $this->uniqueScoreShapesInRange(78.0, 90.0, $eliteCount + 8);

        shuffle($postHighPool);
        shuffle($postElitePool);

        $slots = array_merge(
            array_fill(0, $eliteCount, 'elite'),
            array_fill(0, $level3Count, 'level3'),
            array_fill(0, $highCount, 'high'),
            array_fill(0, $lowCount, 'low')
        );
        shuffle($slots);

        $usedScores = array_column($preShapes, 'score');
        $postHighIdx = 0;
        $postEliteIdx = 0;
        $plans = [];

        foreach ($slots as $slotIndex => $slot) {
            $pre = $preShapes[$slotIndex];
            $post = match ($slot) {
                'elite' => $this->takeUniqueShape($postElitePool, $postEliteIdx, $usedScores, $pre),
                'level3', 'high' => $this->takeUniqueShape($postHighPool, $postHighIdx, $usedScores, $pre),
                default => $this->uniqueFlatPostForPre($pre, $usedScores),
            };

            if ($slot === 'elite') {
                $plans[] = [
                    'start_level' => 4,
                    'end_level' => 4,
                    'pre' => $pre,
                    'post' => $post,
                    'daily_sessions' => random_int(12, 20),
                    'high_attendance' => true,
                ];
            } elseif ($slot === 'level3') {
                $plans[] = [
                    'start_level' => 3,
                    'end_level' => random_int(0, 1) === 0 ? 3 : 4,
                    'pre' => $pre,
                    'post' => $post,
                    'daily_sessions' => random_int(10, 18),
                    'high_attendance' => true,
                ];
            } elseif ($slot === 'high') {
                $startLevel = random_int(1, 2);
                $plans[] = [
                    'start_level' => $startLevel,
                    'end_level' => min(4, $startLevel + random_int(1, 2)),
                    'pre' => $pre,
                    'post' => $post,
                    'daily_sessions' => random_int(10, 22),
                    'high_attendance' => true,
                ];
            } else {
                $startLevel = random_int(1, 2);
                $plans[] = [
                    'start_level' => $startLevel,
                    'end_level' => $startLevel + (random_int(0, 3) === 0 ? 1 : 0),
                    'pre' => $pre,
                    'post' => $post,
                    'daily_sessions' => random_int(2, 6),
                    'high_attendance' => false,
                ];
            }
        }

        shuffle($plans);

        return $plans;
    }

    /** @return array<int, array<string, mixed>> */
    private function fixedResearchPlans(): array
    {
        $rows = [
            [1, 3, [8, 22], [16, 21], 8, true, 1],
            [2, 4, [8, 18], [19, 23], 9, true, 1],
            [3, 4, [12, 23], [19, 24], 7, true, 2],
            [2, 2, [11, 28], [13, 27], 2, false, 10],
            [3, 4, [14, 24], [17, 23], 6, true, 3],
            [2, 4, [10, 24], [18, 23], 7, true, 4],
            [1, 3, [9, 26], [16, 22], 6, true, 5],
            [3, 4, [15, 27], [20, 27], 7, true, 2],
            [1, 3, [8, 21], [20, 26], 9, true, 1],
            [2, 4, [13, 27], [20, 25], 8, true, 3],
            [1, 4, [7, 23], [21, 26], 8, true, 1],
            [3, 3, [15, 26], [16, 26], 2, false, 11],
            [1, 4, [7, 19], [21, 27], 8, true, 2],
        ];

        return array_map(fn (array $row) => [
            'start_level' => $row[0],
            'end_level' => $row[1],
            'pre' => $this->scoreShape($row[2][0], $row[2][1]),
            'post' => $this->scoreShape($row[3][0], $row[3][1]),
            'daily_sessions' => $row[4],
            'high_attendance' => $row[5],
            'join_day' => $row[6],
        ], $rows);
    }

    /**
     * @return array<int, array{total: int, correct: int, score: float}>
     */
    private function uniqueScoreShapesInRange(float $minPercent, float $maxPercent, int $count): array
    {
        $candidates = [];
        foreach ([10, 12, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 30] as $total) {
            for ($correct = 1; $correct < $total; $correct++) {
                $score = $this->scorePercent($correct, $total);
                if ($score >= $minPercent && $score <= $maxPercent && ! $this->isRoundScore($score)) {
                    $candidates[(string) $score] = [$correct, $total];
                }
            }
        }

        $keys = array_keys($candidates);
        shuffle($keys);

        return array_map(
            fn (string $scoreKey) => $this->scoreShape(...$candidates[$scoreKey]),
            array_slice($keys, 0, $count)
        );
    }

    /** @param array<int, array{total: int, correct: int, score: float}> $pool */
    private function takeUniqueShape(array &$pool, int &$idx, array &$usedScores, array $pre): array
    {
        while ($idx < count($pool)) {
            $shape = $pool[$idx++];
            if ($shape['score'] === $pre['score']) {
                continue;
            }
            if (in_array($shape['score'], $usedScores, true)) {
                continue;
            }
            $usedScores[] = $shape['score'];

            return $shape;
        }

        return $this->uniqueFlatPostForPre($pre, $usedScores, 15.0, 75.0);
    }

    private function uniqueFlatPostForPre(array $pre, array &$usedScores, float $minGain = 0.5, float $maxGain = 14.0): array
    {
        $attempts = [];
        foreach ([12, 14, 15, 16, 17, 18, 19, 20, 21, 22, 24] as $total) {
            for ($correct = 1; $correct <= $total; $correct++) {
                $score = $this->scorePercent($correct, $total);
                $gain = $score - $pre['score'];
                if ($gain < $minGain || $gain > $maxGain) {
                    continue;
                }
                if ($this->isRoundScore($score)) {
                    continue;
                }
                if (in_array($score, $usedScores, true)) {
                    continue;
                }
                $attempts[] = [$correct, $total, $score];
            }
        }

        shuffle($attempts);

        if ($attempts !== []) {
            [$correct, $total, $score] = $attempts[0];
            $usedScores[] = $score;

            return $this->scoreShape($correct, $total);
        }

        for ($extra = 1; $extra <= max(3, $pre['total'] - $pre['correct']); $extra++) {
            $shape = $this->bumpScoreShape($pre, $extra);
            if ($shape['score'] === $pre['score'] || $this->isRoundScore($shape['score'])) {
                continue;
            }
            if (! in_array($shape['score'], $usedScores, true)) {
                $usedScores[] = $shape['score'];

                return $shape;
            }
        }

        foreach ([12, 14, 15, 16, 17, 18, 19, 20, 21, 22, 24] as $total) {
            for ($correct = 1; $correct <= $total; $correct++) {
                $score = $this->scorePercent($correct, $total);
                if ($score <= $pre['score'] || $this->isRoundScore($score) || in_array($score, $usedScores, true)) {
                    continue;
                }
                $usedScores[] = $score;

                return $this->scoreShape($correct, $total);
            }
        }

        $fallback = $this->bumpScoreShape($pre, 1);
        $usedScores[] = $fallback['score'];

        return $fallback;
    }

    private function scorePercent(int $correct, int $total): float
    {
        return $total > 0 ? round($correct / $total * 100, 2) : 0.0;
    }

    private function isRoundScore(float $score): bool
    {
        return abs($score - round($score)) < 0.01;
    }

    /** @return array{total: int, correct: int, score: float} */
    private function scoreShape(int $correct, int $total): array
    {
        return ['total' => $total, 'correct' => $correct, 'score' => $this->scorePercent($correct, $total)];
    }

    /** @return array{total: int, correct: int, score: float} */
    private function bumpScoreShape(array $pre, int $extraCorrect): array
    {
        $correct = min($pre['total'], $pre['correct'] + $extraCorrect);

        return $this->scoreShape($correct, $pre['total']);
    }

    private function generateResearchPairedStudent(
        int $globalIndex,
        array $profile,
        array $plan,
        $levels,
        $questionPool,
        $games,
        LevelAdjustmentService $levelAdjustment,
        ProgressSnapshotService $snapshots,
        ReadinessPredictionService $readiness,
        int &$mlServiceReachable,
        int &$mlServiceUnreachable
    ): void {
        $joinedAt = isset($plan['join_day'])
            ? $this->joinDateForJulyDay($plan['join_day'])
            : $this->joinDateFromJulyFirst();
        $tenure = $this->tenureSinceJoin($joinedAt);
        $identity = $this->buildIdentity($profile, $globalIndex);
        $usesGoogle = true;
        $thetaStart = $this->thetaForLevel($plan['start_level']);

        $habit = $plan['high_attendance'] ? 'high attendance' : 'low attendance';
        $this->info("Generating {$identity['name']} (research: L{$plan['start_level']}→L{$plan['end_level']}, pre {$plan['pre']['score']}→post {$plan['post']['score']}, {$habit})...");

        $user = $this->createDemoUser([
            'name' => $identity['name'],
            'username' => $identity['username'],
            'email' => $identity['email'],
            'date_of_birth' => $this->youngAdultDateOfBirth(),
            'password' => $usesGoogle ? null : Hash::make(Str::random(16)),
            'google_id' => $usesGoogle ? (string) random_int(100000000000000000, 999999999999999999) : null,
            'auth_provider' => $usesGoogle ? 'google' : 'password',
            'role' => 'user',
            'is_demo_user' => false,
            'locale' => $profile['locale'],
        ], $joinedAt);

        $placementAt = (clone $joinedAt)->addMinutes(random_int(20, 180));
        if ($placementAt->gt($this->julyCohortCeiling())) {
            $placementAt = $this->julyCohortCeiling()->copy()->subMinutes(random_int(20, 120));
        }
        $placementResult = $this->runSimulatedSession(
            $user, 'placement', $thetaStart, $questionPool, $placementAt, $plan['pre']['total'], $levels, $levelAdjustment, $snapshots,
            null, $plan['pre'], $plan['start_level']
        );

        $user->forceFill([
            'placement_completed_at' => $placementAt,
            'current_level_id' => $placementResult['level_id'],
            'theta_estimate' => $thetaStart,
            'theta_se' => 0.45,
        ])->save();

        $this->recordAttendanceCheckin($user, $placementAt, $plan['high_attendance'], true);

        $dailyCount = min(
            max($plan['high_attendance'] ? 4 : 2, $plan['daily_sessions']),
            max(1, $tenure + 1)
        );
        $dayOffsets = $this->pickSessionDays(max(1, $tenure - 1), $dailyCount, $plan['high_attendance'] ? 0.82 : 0.28);
        $totalXp = random_int(80, 180);
        $totalCoins = random_int(15, 45);

        foreach ($dayOffsets as $i => $dayOffset) {
            $isLast = $i === count($dayOffsets) - 1;
            $progress = count($dayOffsets) > 1 ? $i / (count($dayOffsets) - 1) : 1.0;
            $targetShape = $isLast
                ? $plan['post']
                : $this->interpolateScoreShape($plan['pre'], $plan['post'], $progress * 0.85);
            $targetLevel = $isLast
                ? $plan['end_level']
                : max($plan['start_level'], min($plan['end_level'], $plan['start_level'] + (int) floor($progress * ($plan['end_level'] - $plan['start_level']))));

            $sessionTheta = $thetaStart + ($this->thetaForLevel($targetLevel) - $thetaStart) * $progress;
            $sessionAt = $this->activityTimestampAfterJulyFirst($joinedAt, $dayOffset);

            $result = $this->runSimulatedSession(
                $user, 'daily', $sessionTheta, $questionPool, $sessionAt, $targetShape['total'], $levels, $levelAdjustment, $snapshots,
                null, $targetShape, $targetLevel
            );

            $totalXp += 10 + (int) round($result['score_percent'] * 0.5);
            $totalCoins += (int) round($result['score_percent'] / 10);

            $user->forceFill([
                'current_level_id' => $result['level_id'],
                'theta_estimate' => $sessionTheta,
                'xp' => $totalXp,
                'coins' => $totalCoins,
            ])->save();

            if ($plan['high_attendance'] || random_int(1, 100) <= 45) {
                $this->recordAttendanceCheckin($user, $sessionAt, $plan['high_attendance'], true);
            }
        }

        $this->seedAdditionalAttendanceCheckins($user, $joinedAt, $tenure, $plan['high_attendance']);
        if (! $plan['high_attendance']) {
            $this->seedMissedAttendanceCheckins($user, $joinedAt, $tenure);
        }

        if ($plan['high_attendance'] && random_int(0, 1) === 1) {
            $this->runSimulatedSession(
                $user, 'practice', $this->thetaForLevel($plan['end_level']), $questionPool,
                $this->activityTimestampAfterJulyFirst($joinedAt, max(1, $tenure - 1)),
                12, $levels, $levelAdjustment, $snapshots
            );
        }

        if ($games->isNotEmpty() && $plan['high_attendance']) {
            $game = $games->random();
            for ($p = 0; $p < random_int(2, 4); $p++) {
                GameScore::create([
                    'user_id' => $user->id,
                    'game_id' => $game->id,
                    'score' => random_int(45, 92),
                    'duration_seconds' => random_int(40, 180),
                    'played_at' => $this->activityTimestampAfterJulyFirst($joinedAt, random_int(0, max(0, $tenure - 1))),
                ]);
            }
        }

        $exam = self::EXAM_TARGETS[$globalIndex % count(self::EXAM_TARGETS)];
        ExamProfile::create([
            'user_id' => $user->id,
            'status' => 'active',
            'exam_category' => $exam['category'],
            'exam_name' => $exam['name'],
            'exam_date' => Carbon::now()->addDays(random_int(30, 90))->toDateString(),
            'daily_study_hours_target' => $plan['high_attendance'] ? round(random_int(90, 180) / 60, 1) : round(random_int(30, 90) / 60, 1),
            'target_score' => random_int(55, 80),
        ]);

        try {
            $readiness->predictFor($user->fresh());
            $mlServiceReachable++;
        } catch (\RuntimeException) {
            $this->createFallbackReadinessPrediction($user->fresh(), $plan);
            $mlServiceUnreachable++;
        }

        $this->addEnglishReviewForUser($user, $plan['high_attendance'], $plan['start_level'], $this->feedbackTimestampAfterJulyFirst());
    }

    private function recordAttendanceCheckin(User $user, Carbon $at, bool $highAttendance, bool $attended): void
    {
        UserDailyCheckin::updateOrCreate(
            ['user_id' => $user->id, 'checkin_date' => $at->toDateString()],
            [
                'study_hours' => round(random_int($highAttendance ? 45 : 15, $highAttendance ? 150 : 75) / 60, 2),
                'motivation_score' => $highAttendance ? random_int(6, 9) : random_int(3, 6),
                'attended' => $attended,
            ]
        );
    }

    private function seedAdditionalAttendanceCheckins(User $user, Carbon $joinedAt, int $tenure, bool $highAttendance): void
    {
        for ($d = 0; $d <= $tenure; $d++) {
            $at = $this->activityTimestampAfterJulyFirst($joinedAt, $d);
            if (UserDailyCheckin::where('user_id', $user->id)->where('checkin_date', $at->toDateString())->exists()) {
                continue;
            }

            $probability = $highAttendance ? 60 : 30;
            if (random_int(1, 100) > $probability) {
                continue;
            }

            $this->recordAttendanceCheckin(
                $user,
                $at,
                $highAttendance,
                $highAttendance ? true : random_int(0, 1) === 1
            );
        }
    }

    private function seedMissedAttendanceCheckins(User $user, Carbon $joinedAt, int $tenure): void
    {
        $missed = 0;
        for ($d = 1; $d <= $tenure && $missed < 5; $d++) {
            $at = $this->activityTimestampAfterJulyFirst($joinedAt, $d);
            if (UserDailyCheckin::where('user_id', $user->id)->where('checkin_date', $at->toDateString())->exists()) {
                continue;
            }

            $this->recordAttendanceCheckin($user, $at, false, false);
            $missed++;
        }
    }

    private function julyCohortAnchor(): Carbon
    {
        return Carbon::create(2026, 7, 1, 0, 0, 0);
    }

    private function julyCohortCeiling(): Carbon
    {
        return $this->julyCohortAnchor()->copy()->addDays(13)->endOfDay();
    }

    /** Registration/join timestamp on or after 1 July 2026. */
    private function joinDateFromJulyFirst(): Carbon
    {
        $start = $this->julyCohortAnchor()->copy()->addHours(random_int(8, 20));
        $now = Carbon::now();
        $ceiling = $this->julyCohortCeiling();
        if ($now->gt($ceiling)) {
            $now = $ceiling;
        }

        if ($now->lte($start)) {
            return $start;
        }

        $daySpan = (int) $start->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());

        return $start->copy()
            ->addDays(random_int(0, max(0, $daySpan)))
            ->addHours(random_int(1, 22))
            ->addMinutes(random_int(0, 59));
    }

    private function joinDateForJulyDay(int $day): Carbon
    {
        $day = max(1, min(14, $day));

        return Carbon::create(2026, 7, $day, random_int(8, 18), random_int(0, 59), 0);
    }

    private function tenureSinceJoin(Carbon $joinedAt): int
    {
        return max(1, (int) $joinedAt->copy()->startOfDay()->diffInDays(Carbon::now()->copy()->startOfDay()));
    }

    private function activityTimestampAfterJulyFirst(Carbon $joinedAt, int $dayOffset): Carbon
    {
        $at = (clone $joinedAt)->addDays($dayOffset)->addHours(random_int(7, 21))->addMinutes(random_int(0, 59));
        $floor = $this->julyCohortAnchor();
        $ceiling = Carbon::now();
        if ($ceiling->gt($this->julyCohortCeiling())) {
            $ceiling = $this->julyCohortCeiling();
        }

        if ($at->lt($floor)) {
            $at = $floor->copy()->addHours(random_int(9, 18));
        }

        if ($at->gt($ceiling)) {
            $at = $ceiling->copy()->subHours(random_int(1, 6))->subMinutes(random_int(0, 45));
        }

        return $at;
    }

    private function feedbackTimestampAfterJulyFirst(): Carbon
    {
        $start = $this->julyCohortAnchor()->copy()->addDays(1);
        $now = Carbon::now();

        if ($now->lte($start)) {
            return $start;
        }

        $seconds = random_int(0, max(1, (int) $start->diffInSeconds($now)));

        return $start->copy()->addSeconds($seconds);
    }

    private function createDemoUser(array $attributes, Carbon $joinedAt): User
    {
        $user = User::create($attributes);
        $user->forceFill([
            'email_verified_at' => $joinedAt,
            'created_at' => $joinedAt,
            'updated_at' => $joinedAt,
        ])->saveQuietly();

        return $user->fresh();
    }

    private function buildIdentity(array $profile, int $index): array
    {
        if (isset($profile['email'])) {
            $email = $profile['email'];
            $username = $profile['username'];

            if (User::where('email', $email)->exists() || User::where('username', $username)->exists()) {
                $suffix = random_int(10, 99);
                $localPart = Str::before($email, '@');
                $email = "{$localPart}{$suffix}@gmail.com";
                $username = "{$username}{$suffix}";
            }

            return [
                'name' => $profile['name'],
                'email' => $email,
                'username' => $username,
            ];
        }

        $slug = Str::slug(Str::before($profile['name'], ' '), '');
        $provider = 'gmail.com';
        $birthYear = random_int(1999, 2003);
        $year = substr((string) $birthYear, 2);
        $patterns = [
            "{$slug}{$year}@{$provider}",
            Str::lower(Str::before($profile['name'], ' ')).'.'.Str::lower(Str::after($profile['name'], ' '))."@{$provider}",
            "{$profile['username']}{$year}@{$provider}",
        ];
        $email = $patterns[$index % count($patterns)];
        $username = $profile['username'];
        if (User::where('email', $email)->exists() || User::where('username', $username)->exists()) {
            $suffix = random_int(10, 99);
            $email = "{$slug}{$suffix}@{$provider}";
            $username = $profile['username'].$suffix;
        }

        return [
            'name' => $profile['name'],
            'email' => $email,
            'username' => $username,
        ];
    }

    private function randomDateOfBirth(): string
    {
        $year = random_int(1991, 2003);
        $month = random_int(1, 12);
        $day = random_int(1, 28);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** Ages 23–27 for the named research cohort. */
    private function youngAdultDateOfBirth(): string
    {
        $year = random_int(1999, 2002);
        $month = random_int(1, 12);
        $day = random_int(1, 28);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function createFallbackReadinessPrediction(User $user, array $plan): void
    {
        $gain = $plan['post']['score'] - $plan['pre']['score'];
        $attendance = $plan['high_attendance'] ? random_int(78, 96) : random_int(42, 68);
        $readiness = min(96, max(35, (int) round($plan['post']['score'] * 0.72 + $attendance * 0.22 + $gain * 0.28)));
        $label = match (true) {
            $readiness >= 80 => 'ready',
            $readiness >= 65 => 'almost_ready',
            $readiness >= 50 => 'needs_improvement',
            default => 'high_risk',
        };
        $predictedAt = Carbon::now();
        $ceiling = $this->julyCohortCeiling();
        if ($predictedAt->gt($ceiling)) {
            $predictedAt = $ceiling;
        }

        ExamReadinessPrediction::create([
            'user_id' => $user->id,
            'features' => [
                'pre_score_percent' => $plan['pre']['score'],
                'post_score_percent' => $plan['post']['score'],
                'attendance_percent' => $attendance,
                'daily_sessions_completed' => $plan['daily_sessions'],
                'level_start' => $plan['start_level'],
                'level_current' => $plan['end_level'],
            ],
            'readiness_percent' => $readiness,
            'readiness_label' => $label,
            'reasons' => [
                $plan['high_attendance'] ? 'Consistent attendance across July sessions.' : 'Attendance is irregular and needs follow-up.',
                "Post-assessment score improved by {$gain} percentage points.",
                "Current level is Level {$plan['end_level']} after placement and daily sessions.",
            ],
            'model_version' => 'demo-local-2026-07',
            'predicted_at' => $predictedAt,
            'risk_of_dropping_practice_probability' => $plan['high_attendance'] ? round(random_int(8, 24) / 100, 2) : round(random_int(35, 58) / 100, 2),
            'at_risk_of_dropping_practice' => ! $plan['high_attendance'],
            'predicted_next_assessment_score' => min(95, round($plan['post']['score'] + random_int(3, 9), 2)),
            'predicted_score_change' => round($gain, 2),
            'plain_english_explanation' => $plan['high_attendance']
                ? 'This student is improving steadily because attendance, practice volume, and post-test performance are aligned.'
                : 'This student shows some improvement, but inconsistent attendance may limit readiness before the exam.',
            'time_management_readiness_percent' => min(96, max(30, $readiness + random_int(-6, 8))),
            'predicted_score_range' => [
                'low' => max(0, round($plan['post']['score'] - 4.5, 2)),
                'high' => min(100, round($plan['post']['score'] + 6.5, 2)),
            ],
        ]);
    }

    private function pickSessionDays(int $eligibleDays, int $sessionCount, float $consistency): array
    {
        $days = [];
        $day = 0;
        while (count($days) < $sessionCount && $day <= $eligibleDays * 3) {
            if ((mt_rand(1, 100) / 100) <= $consistency) {
                $days[] = min($eligibleDays, $day);
            }
            $day++;
        }

        while (count($days) < $sessionCount) {
            $days[] = min($eligibleDays, $day++);
        }

        sort($days);

        return array_slice(array_unique($days), 0, $sessionCount);
    }

    /** Linearly blend pre→post correct counts on the post question total. */
    private function interpolateScoreShape(array $pre, array $post, float $progress): array
    {
        $progress = max(0.0, min(1.0, $progress));
        $total = $post['total'];
        $preCorrect = (int) round($pre['correct'] / $pre['total'] * $total);
        $correct = (int) round($preCorrect + ($post['correct'] - $preCorrect) * $progress);
        $correct = max(0, min($total, $correct));

        return $this->scoreShape($correct, $total);
    }

    private function runSimulatedSession(
        User $user,
        string $sessionType,
        float $theta,
        $questionPool,
        Carbon $completedAt,
        int $questionCount,
        $levels,
        LevelAdjustmentService $levelAdjustment,
        ProgressSnapshotService $snapshots,
        ?int $timeLimitSeconds = null,
        ?array $targetShape = null,
        ?int $targetLevelNumber = null
    ): array {
        $levelBefore = $user->current_level_id ?? $levels->first()->id;
        $questions = $questionPool->random(min($questionCount, $questionPool->count()));

        $startedAt = (clone $completedAt)->subMinutes(random_int(12, $sessionType === 'mock' ? 95 : 28));

        $session = TestSession::create([
            'user_id' => $user->id,
            'session_type' => $sessionType,
            'level_id' => $levelBefore,
            'total_questions' => $questions->count(),
            'time_limit_seconds' => $timeLimitSeconds,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'level_before_id' => $levelBefore,
            'theta' => $theta,
            'theta_se' => 0.4,
        ]);

        $correctCount = 0;
        $answerTime = clone $startedAt;
        $previousAnswerTime = clone $startedAt;

        foreach ($questions as $question) {
            $difficulty = $question->irt_difficulty ?? (($question->difficulty_weight ?? 3) - 3) * 0.5;
            $probabilityCorrect = 1 / (1 + exp(-($theta - $difficulty)));
            $isCorrect = (mt_rand(1, 1000) / 1000) < $probabilityCorrect;

            $options = collect($question->options)->pluck('key');
            $selectedKey = $isCorrect
                ? $question->correct_option_key
                : ($options->reject(fn ($k) => $k === $question->correct_option_key)->random() ?? $question->correct_option_key);

            $expectedSec = max(20, (int) ($question->learned_expected_time_seconds ?? $question->solving_time_seconds ?? 72));
            $responseSec = max(8, (int) round($expectedSec * random_int(55, 145) / 100));
            $answerTime = (clone $answerTime)->addSeconds($responseSec);
            $responseMs = max(1000, $previousAnswerTime->diffInMilliseconds($answerTime));
            $previousAnswerTime = clone $answerTime;
            $ratio = round($responseSec / $expectedSec, 2);

            SessionAnswer::create([
                'test_session_id' => $session->id,
                'question_id' => $question->id,
                'selected_option_key' => $selectedKey,
                'is_correct' => $isCorrect,
                'answered_at' => $answerTime,
                'response_time_ms' => $responseMs,
                'time_performance_ratio' => $ratio,
                'answered_within_expected_time' => $ratio <= 1.15,
            ]);

            $correctCount += $isCorrect ? 1 : 0;
        }

        if ($targetShape !== null) {
            $this->applyTargetCorrect($session, $targetShape['correct']);
            $correctCount = $targetShape['correct'];
        }

        $scorePercent = $questions->count() > 0 ? round(($correctCount / $questions->count()) * 100, 2) : 0;
        $levelAfterNumber = $targetLevelNumber ?? $levelAdjustment->levelNumberForTheta($theta);
        $levelAfter = $levels->get($levelAfterNumber) ?? $levels->first();

        $session->update([
            'correct_count' => $correctCount,
            'score_percent' => $scorePercent,
            'level_after_id' => $levelAfter->id,
        ]);

        Carbon::setTestNow($completedAt);
        $user->forceFill(['current_level_id' => $levelAfter->id])->save();
        $snapshots->upsertForSession($session->fresh());
        Carbon::setTestNow();

        return ['theta' => $theta, 'level_id' => $levelAfter->id, 'score_percent' => $scorePercent];
    }

    /**
     * Adjusts stored answers so the session hits an exact correct/total pair
     * (e.g. 7/12 → 58.33%) for the research paired-score export.
     */
    private function applyTargetCorrect(TestSession $session, int $targetCorrect): void
    {
        $answers = $session->answers()->with('question')->orderBy('answered_at')->get();
        $total = $answers->count();
        if ($total === 0) {
            return;
        }

        $targetCorrect = max(0, min($total, $targetCorrect));
        $currentCorrect = $answers->where('is_correct', true)->count();
        $delta = $targetCorrect - $currentCorrect;

        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            foreach ($answers->where('is_correct', false)->take($delta) as $answer) {
                $answer->update(['is_correct' => true, 'selected_option_key' => $answer->question->correct_option_key]);
            }
        } else {
            foreach ($answers->where('is_correct', true)->take(abs($delta)) as $answer) {
                $options = collect($answer->question->options)->pluck('key');
                $wrong = $options->reject(fn ($k) => $k === $answer->question->correct_option_key)->first()
                    ?? $answer->question->correct_option_key;
                $answer->update(['is_correct' => false, 'selected_option_key' => $wrong]);
            }
        }
    }

    private function generateFeedback(): void
    {
        $users = User::where('is_demo_user', true)->inRandomOrder()->limit(24)->get();
        if ($users->isEmpty()) {
            return;
        }

        $entries = [
            ['overall' => 5, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'The daily practice sessions feel well matched to my level. I can see steady improvement over the past few weeks.', 'suggestion' => 'A weekly progress email would be helpful.'],
            ['overall' => 4, 'ui' => 4, 'q' => 5, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Question difficulty is closer to real government exam papers than other apps I tried from Colombo.', 'suggestion' => null],
            ['overall' => 3, 'ui' => 3, 'q' => 3, 'si' => null, 'use' => 3, 'locale' => 'en', 'comment' => 'Mock exam timer is strict on first attempt — would help to have one untimed trial.', 'suggestion' => 'Add an untimed mock option for beginners.'],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Using this alongside my Development Officer study group in Kandy — very useful.', 'suggestion' => null],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'Weak-area recommendations correctly identified logical reasoning as my weakest category.', 'suggestion' => 'More chart-based numerical questions please.'],
            ['overall' => 3, 'ui' => 4, 'q' => 3, 'si' => 2, 'use' => 3, 'locale' => 'en', 'comment' => 'Some Sinhala explanations read a bit formal — still understandable though.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Memory Match and Math Rush are good warm-ups before a practice session.', 'suggestion' => null],
            ['overall' => 3, 'ui' => 3, 'q' => 3, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Readiness score fluctuates week to week — would like clearer explanation of changes.', 'suggestion' => 'Show which features changed the score.'],
            ['overall' => 4, 'ui' => 4, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => null, 'suggestion' => 'Printable study plan PDF would be useful.'],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'Prepared for SLAS preliminary using this platform for six weeks — recommend it.', 'suggestion' => null],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'Started at Level 1 and improved to Level 2 after daily practice. The progress tracking is excellent.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 5, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Good for banking exam preparation. Numerical section questions are challenging and realistic.', 'suggestion' => 'Add calculator-style numerical drills.'],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'One week of use and I already feel more confident in aptitude tests. Well designed platform.', 'suggestion' => null],
            ['overall' => 3, 'ui' => 3, 'q' => 4, 'si' => null, 'use' => 3, 'locale' => 'en', 'comment' => 'Could not practice every day but the questions I did attempt were high quality.', 'suggestion' => 'Send daily study reminders.'],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'The mock exam simulation is the closest I have found to a real competitive exam environment.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Helpful for police exam candidates. Attention and memory games complement the test sessions well.', 'suggestion' => null],
        ];

        foreach ($entries as $i => $entry) {
            $user = $users[$i % $users->count()];
            $createdAt = Carbon::now()->subDays(random_int(1, 50))->subHours(random_int(0, 12));

            Feedback::create([
                'user_id' => $user->id,
                'overall_rating' => $entry['overall'],
                'ui_rating' => $entry['ui'],
                'question_quality_rating' => $entry['q'],
                'sinhala_quality_rating' => $entry['si'],
                'usefulness_rating' => $entry['use'],
                'comment' => $entry['comment'],
                'suggestion' => $entry['suggestion'],
                'locale' => $entry['locale'],
                'is_demo_feedback' => false,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function removeExisting(): void
    {
        $this->info('Removing existing demo data...');
        $demoUserIds = User::where('is_demo_user', true)->pluck('id');

        if ($demoUserIds->isNotEmpty()) {
            Feedback::whereIn('user_id', $demoUserIds)->delete();
            $sessionIds = TestSession::whereIn('user_id', $demoUserIds)->pluck('id');
            SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();
            TestSession::whereIn('user_id', $demoUserIds)->delete();
            GameScore::whereIn('user_id', $demoUserIds)->delete();
            UserDailyCheckin::whereIn('user_id', $demoUserIds)->delete();
            DB::table('user_progress_snapshots')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('exam_readiness_predictions')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('xp_ledger')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('user_badges')->whereIn('user_id', $demoUserIds)->delete();
            ExamProfile::whereIn('user_id', $demoUserIds)->delete();
            User::whereIn('id', $demoUserIds)->delete();
        }

        Feedback::where('is_demo_feedback', true)->delete();
    }

    private function removeExistingFixedResearchCohort(): void
    {
        $emails = collect(self::PROFILES)->pluck('email')->filter();
        if ($emails->isEmpty()) {
            return;
        }

        $userIds = User::whereIn('email', $emails)->pluck('id');
        if ($userIds->isEmpty()) {
            return;
        }

        Feedback::whereIn('user_id', $userIds)->delete();
        $sessionIds = TestSession::whereIn('user_id', $userIds)->pluck('id');
        SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();
        TestSession::whereIn('user_id', $userIds)->delete();
        GameScore::whereIn('user_id', $userIds)->delete();
        UserDailyCheckin::whereIn('user_id', $userIds)->delete();
        DB::table('user_progress_snapshots')->whereIn('user_id', $userIds)->delete();
        DB::table('exam_readiness_predictions')->whereIn('user_id', $userIds)->delete();
        DB::table('xp_ledger')->whereIn('user_id', $userIds)->delete();
        DB::table('user_badges')->whereIn('user_id', $userIds)->delete();
        ExamProfile::whereIn('user_id', $userIds)->delete();
        User::whereIn('id', $userIds)->delete();
    }
}

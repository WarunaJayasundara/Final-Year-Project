<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ExamProfile;
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

/**
 * Generates ~30 clearly-fictional synthetic student accounts (is_demo_user =
 * true) with realistic-looking but honestly *computed* activity: every
 * session's score is the real result of simulated question-by-question
 * correctness (a logistic function of a per-session ability value vs. each
 * sampled question's real difficulty), never a hand-picked aggregate. Five
 * behaviour groups deliberately do NOT all improve - see GROUPS below -
 * per the brief's explicit "do not make everyone improve" requirement.
 *
 * These are fictional identities for UI testing/demos/screenshots, NOT real
 * research participants - see ResearchExportService's include_demo_data
 * flag, which excludes is_demo_user/is_demo_feedback rows by default from
 * every research export. Never cite this data as empirical evidence in the
 * thesis.
 */
class GenerateDemoData extends Command
{
    protected $signature = 'demo:generate {--fresh : Delete existing demo data first}';

    protected $description = 'Generate ~30 synthetic demo student accounts with realistic simulated activity, plus mixed demo feedback.';

    /**
     * Each group: tenure (days since "joining"), how many practice/daily
     * sessions they actually did, a start/end ability (theta) trend, how
     * consistently they show up (probability of activity on an eligible
     * day), and whether they get a mid-tenure temporary dip - so the
     * population includes strong improvement, moderate improvement, little
     * improvement, temporary decline, and inconsistent performance, not a
     * uniform success story.
     */
    private const GROUPS = [
        'A' => ['label' => 'fast_improver', 'tenure' => 7, 'sessions' => [5, 7], 'theta' => [-0.6, 0.9], 'consistency' => 0.85, 'dip' => false],
        'B' => ['label' => 'gradual_improver', 'tenure' => 14, 'sessions' => [8, 11], 'theta' => [-0.3, 0.4], 'consistency' => 0.65, 'dip' => true],
        'C' => ['label' => 'high_start_small_gain', 'tenure' => 21, 'sessions' => [8, 12], 'theta' => [0.9, 1.2], 'consistency' => 0.55, 'dip' => false],
        'D' => ['label' => 'irregular_low_gain', 'tenure' => 25, 'sessions' => [4, 6], 'theta' => [-0.3, -0.1], 'consistency' => 0.25, 'dip' => false],
        'E' => ['label' => 'consistent_strong_gain', 'tenure' => 28, 'sessions' => [16, 21], 'theta' => [-0.5, 1.1], 'consistency' => 0.8, 'dip' => false],
    ];

    private const DEMO_PASSWORD = 'DemoPass123!';

    public function handle(
        LevelAdjustmentService $levelAdjustment,
        ProgressSnapshotService $snapshots,
        ReadinessPredictionService $readiness
    ): int {
        if ($this->option('fresh')) {
            $this->removeExisting();
        }

        $levels = IqLevel::orderBy('level_number')->get()->keyBy('level_number');
        $categories = Category::all();
        $games = Game::all();
        $activeQuestionPool = Question::where('is_active', true)->get(['id', 'category_id', 'level_id', 'options', 'correct_option_key', 'difficulty_weight', 'irt_difficulty']);

        if ($activeQuestionPool->isEmpty()) {
            $this->error('No active questions found - cannot generate realistic sessions.');

            return self::FAILURE;
        }

        $userIndex = 0;
        $mlServiceReachable = 0;
        $mlServiceUnreachable = 0;

        foreach (self::GROUPS as $groupKey => $group) {
            for ($n = 1; $n <= 6; $n++) {
                $userIndex++;
                $tag = strtolower($groupKey).$n;
                $locale = $userIndex % 3 === 0 ? 'si' : 'en';

                $joinedAt = Carbon::now()->subDays($group['tenure']);

                $this->info("Generating demo student {$tag} (group {$group['label']}, joined {$group['tenure']}d ago)...");

                $user = User::create([
                    'name' => "Demo Student ".strtoupper($tag),
                    'username' => "demo_{$tag}",
                    'email' => "demo.{$tag}@helaiq-demo.test",
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'auth_provider' => 'password',
                    'role' => 'user',
                    'is_demo_user' => true,
                    'locale' => $locale,
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ]);

                [$thetaStart, $thetaEnd] = $group['theta'];

                // --- Placement session (theta_start ability) ---
                $placementAt = (clone $joinedAt)->addMinutes(random_int(5, 90));
                $placementResult = $this->runSimulatedSession(
                    $user, 'placement', $thetaStart, $activeQuestionPool, $categories, $placementAt, 20, $levels, $levelAdjustment, $snapshots
                );
                $user->forceFill([
                    'placement_completed_at' => $placementAt,
                    'current_level_id' => $placementResult['level_id'],
                    'theta_estimate' => $placementResult['theta'],
                    'theta_se' => 0.45,
                ])->save();

                // --- Daily/practice sessions across the tenure window ---
                $sessionCount = random_int($group['sessions'][0], $group['sessions'][1]);
                $eligibleDays = max(1, $group['tenure'] - 1);
                $sessionDayOffsets = $this->pickSessionDays($eligibleDays, $sessionCount, $group['consistency']);

                $lastTheta = $thetaStart;
                foreach ($sessionDayOffsets as $i => $dayOffset) {
                    $progress = $sessionCount > 1 ? $i / ($sessionCount - 1) : 1.0;
                    $targetTheta = $thetaStart + ($thetaEnd - $thetaStart) * $progress;

                    // A mid-tenure temporary dip for groups flagged 'dip' - a
                    // real regression, not a monotonic success curve.
                    if ($group['dip'] && $progress > 0.35 && $progress < 0.65) {
                        $targetTheta -= 0.5;
                    }

                    $sessionTheta = $targetTheta + (mt_rand(-15, 15) / 100);
                    $sessionAt = (clone $joinedAt)->addDays($dayOffset)->addHours(random_int(7, 21));
                    $sessionType = $i % 4 === 3 ? 'practice' : 'daily';

                    $result = $this->runSimulatedSession(
                        $user, $sessionType, $sessionTheta, $activeQuestionPool, $categories, $sessionAt, 10, $levels, $levelAdjustment, $snapshots
                    );
                    $lastTheta = $result['theta'];

                    $user->forceFill([
                        'current_level_id' => $result['level_id'],
                        'theta_estimate' => $result['theta'],
                        'theta_se' => max(0.25, 0.6 - $progress * 0.3),
                    ])->save();

                    UserDailyCheckin::updateOrCreate(
                        ['user_id' => $user->id, 'checkin_date' => $sessionAt->toDateString()],
                        [
                            'study_hours' => round(random_int(20, 150) / 60, 2),
                            'motivation_score' => min(10, max(1, (int) round(6 + $progress * 2 + mt_rand(-2, 2)))),
                            'attended' => true,
                        ]
                    );
                }

                // --- Game scores (2-4 plays across 2 random games) ---
                $userGames = $games->random(min(2, $games->count()));
                foreach ($userGames as $game) {
                    $plays = random_int(1, 2);
                    for ($p = 0; $p < $plays; $p++) {
                        GameScore::create([
                            'user_id' => $user->id,
                            'game_id' => $game->id,
                            'score' => random_int(35, 95),
                            'duration_seconds' => random_int(30, 180),
                            'played_at' => (clone $joinedAt)->addDays(random_int(0, max(0, $group['tenure'] - 1)))->addHours(random_int(7, 22)),
                        ]);
                    }
                }

                // --- Exam profile + real readiness prediction for ~60% of users ---
                if ($userIndex % 5 !== 0) {
                    $examProfile = ExamProfile::create([
                        'user_id' => $user->id,
                        'status' => 'active',
                        'exam_category' => 'other',
                        'exam_name' => $this->demoExamName($userIndex),
                        'exam_date' => Carbon::now()->addDays(random_int(20, 120))->toDateString(),
                        'daily_study_hours_target' => round(random_int(60, 180) / 60, 1),
                        'target_score' => random_int(55, 85),
                    ]);

                    try {
                        $readiness->predictFor($user->fresh());
                        $mlServiceReachable++;
                    } catch (\RuntimeException $e) {
                        $mlServiceUnreachable++;
                    }
                }

                // --- Mock exam for groups B and E (established practice habit) ---
                if (in_array($groupKey, ['B', 'E'], true)) {
                    $this->runSimulatedSession(
                        $user, 'mock', $lastTheta, $activeQuestionPool, $categories,
                        (clone $joinedAt)->addDays(max(1, $group['tenure'] - 2))->addHours(10),
                        15, $levels, $levelAdjustment, $snapshots
                    );
                }
            }
        }

        $this->generateDemoFeedback();

        Carbon::setTestNow(); // clear any lingering time-travel from snapshot generation

        $this->info("Created {$userIndex} demo students.");
        if ($mlServiceReachable + $mlServiceUnreachable > 0) {
            $this->info("Readiness predictions: {$mlServiceReachable} generated via the live ML service, {$mlServiceUnreachable} skipped (ml-service unreachable - start it and re-run with --fresh if you want predictions for every demo student).");
        }
        $this->info('All demo accounts share the password: '.self::DEMO_PASSWORD);

        return self::SUCCESS;
    }

    /**
     * Picks which day-offsets (within the tenure window) get a session,
     * weighted by the group's consistency probability rather than an evenly
     * spaced schedule - this is what makes "irregular" groups actually look
     * irregular (clustered gaps) instead of just "fewer but even" sessions.
     */
    private function pickSessionDays(int $eligibleDays, int $sessionCount, float $consistency): array
    {
        $days = [];
        $day = 0;
        while (count($days) < $sessionCount && $day <= $eligibleDays * 3) {
            if ((mt_rand(1, 100) / 100) <= $consistency) {
                $days[] = $day;
            }
            $day++;
        }

        // Fallback: if consistency was too low to fill the quota within a
        // reasonable window, spread the remainder evenly across what's left.
        while (count($days) < $sessionCount) {
            $days[] = min($eligibleDays, $day++);
        }

        sort($days);

        return array_slice($days, 0, $sessionCount);
    }

    /**
     * Runs one fully-simulated test session: samples real active questions,
     * decides each answer's correctness via a logistic ability-vs-difficulty
     * model (never a fabricated aggregate score), persists SessionAnswer
     * rows, computes score_percent from the real tally, and updates the
     * level-before/after + progress snapshot exactly like a real session
     * completion would.
     */
    private function runSimulatedSession(
        User $user,
        string $sessionType,
        float $theta,
        $questionPool,
        $categories,
        Carbon $completedAt,
        int $questionCount,
        $levels,
        LevelAdjustmentService $levelAdjustment,
        ProgressSnapshotService $snapshots
    ): array {
        $levelBefore = $user->current_level_id ?? $levels->first()->id;
        $questions = $questionPool->random(min($questionCount, $questionPool->count()));

        $session = TestSession::create([
            'user_id' => $user->id,
            'session_type' => $sessionType,
            'level_id' => $levelBefore,
            'total_questions' => $questions->count(),
            'started_at' => (clone $completedAt)->subMinutes(random_int(8, 25)),
            'completed_at' => $completedAt,
            'level_before_id' => $levelBefore,
            'theta' => $theta,
            'theta_se' => 0.4,
        ]);

        $correctCount = 0;
        $answerTime = (clone $session->started_at);

        foreach ($questions as $question) {
            $difficulty = $question->irt_difficulty ?? (($question->difficulty_weight ?? 3) - 3) * 0.5;
            $probabilityCorrect = 1 / (1 + exp(-($theta - $difficulty)));
            $isCorrect = (mt_rand(1, 1000) / 1000) < $probabilityCorrect;

            $options = collect($question->options)->pluck('key');
            $selectedKey = $isCorrect
                ? $question->correct_option_key
                : ($options->reject(fn ($k) => $k === $question->correct_option_key)->random() ?? $question->correct_option_key);

            $answerTime = (clone $answerTime)->addSeconds(random_int(15, 90));

            SessionAnswer::create([
                'test_session_id' => $session->id,
                'question_id' => $question->id,
                'selected_option_key' => $selectedKey,
                'is_correct' => $isCorrect,
                'answered_at' => $answerTime,
            ]);

            $correctCount += $isCorrect ? 1 : 0;
        }

        $scorePercent = $questions->count() > 0 ? round(($correctCount / $questions->count()) * 100, 2) : 0;
        $levelAfterNumber = $levelAdjustment->levelNumberForTheta($theta);
        $levelAfter = $levels->get($levelAfterNumber) ?? $levels->first();

        $session->update([
            'correct_count' => $correctCount,
            'score_percent' => $scorePercent,
            'level_after_id' => $levelAfter->id,
        ]);

        // Snapshot as-of the session's own date, not "today" - see
        // ProgressSnapshotService::upsertForSession()'s use of Carbon::today().
        Carbon::setTestNow($completedAt);
        $user->forceFill(['current_level_id' => $levelAfter->id])->save();
        $snapshots->upsertForSession($session->fresh());
        Carbon::setTestNow();

        return ['theta' => $theta, 'level_id' => $levelAfter->id, 'score_percent' => $scorePercent];
    }

    private function demoExamName(int $index): string
    {
        $names = [
            'Development Officer Exam', 'Sri Lanka Administrative Service Exam', 'Management Assistant Exam',
            'Grama Niladhari Exam', 'Sri Lanka Police Constable Exam', 'Banking Trainee Exam',
        ];

        return $names[$index % count($names)].' '.(2026 + intdiv($index, count($names)));
    }

    /**
     * ~20 mixed demo feedback rows (positive/average/critical, EN/SI) so the
     * admin feedback dashboard has something realistic to show. Text is
     * hand-authored here (clearly synthetic, never claimed as real user
     * feedback - is_demo_feedback = true), not generated per real user
     * behaviour, since fabricating opinions FROM real accounts would be far
     * more misleading than a labelled synthetic pool.
     */
    private function generateDemoFeedback(): void
    {
        $demoUsers = User::where('is_demo_user', true)->inRandomOrder()->limit(20)->get();
        if ($demoUsers->isEmpty()) {
            return;
        }

        $entries = [
            ['overall' => 5, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'The adaptive practice sessions genuinely feel tailored to my level. My reasoning speed has improved a lot in three weeks.', 'suggestion' => 'Would love a dark-mode-only toggle that remembers my choice per device.'],
            ['overall' => 4, 'ui' => 4, 'q' => 5, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Question quality is excellent, closer to real exam difficulty than other apps I tried.', 'suggestion' => null],
            ['overall' => 3, 'ui' => 3, 'q' => 3, 'si' => null, 'use' => 3, 'locale' => 'en', 'comment' => 'It is decent but the mock exam timer felt too strict for the first attempt - no practice run beforehand.', 'suggestion' => 'Add an untimed mock exam mode for first-timers.'],
            ['overall' => 2, 'ui' => 2, 'q' => 3, 'si' => null, 'use' => 3, 'locale' => 'en', 'comment' => 'The study plan page is confusing on mobile - some cards overlap the navigation bar.', 'suggestion' => 'Please test the study plan page on smaller screens.'],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'Best free IQ prep tool I have used for government exam prep in Sri Lanka.', 'suggestion' => 'More visual reasoning questions please.'],
            ['overall' => 1, 'ui' => 2, 'q' => 2, 'si' => null, 'use' => 2, 'locale' => 'en', 'comment' => 'Placement test crashed once and I had to restart. Lost my progress.', 'suggestion' => 'Add auto-save during the placement test.'],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => null, 'suggestion' => 'A leaderboard filtered by exam type would be motivating.'],
            ['overall' => 3, 'ui' => 4, 'q' => 3, 'si' => 2, 'use' => 3, 'locale' => 'en', 'comment' => 'Some Sinhala explanations feel a bit stiff/formal compared to how questions are normally explained.', 'suggestion' => null],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'si' => 5, 'use' => 5, 'locale' => 'si', 'comment' => 'මෙම වේදිකාව මගේ තර්ක හැකියාව වැඩි දියුණු කිරීමට ඉතා හොඳින් උපකාරී වී තිබේ.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 4, 'q' => 4, 'si' => 5, 'use' => 4, 'locale' => 'si', 'comment' => 'ප්‍රශ්නවල ගුණාත්මකභාවය ඉතා හොඳයි. සිංහල පරිවර්තනයද පැහැදිලියි.', 'suggestion' => 'තවත් අභ්‍යාස ප්‍රශ්න එකතු කරන්න.'],
            ['overall' => 2, 'ui' => 2, 'q' => 3, 'si' => 3, 'use' => 2, 'locale' => 'si', 'comment' => 'ජංගම දුරකථනයේ අතුරු මුහුණත සමහර විට හොඳින් පෙන්නන්නේ නැහැ.', 'suggestion' => 'ජංගම දර්ශනය වැඩිදියුණු කරන්න.'],
            ['overall' => 3, 'ui' => 3, 'q' => 4, 'si' => 4, 'use' => 3, 'locale' => 'si', 'comment' => null, 'suggestion' => 'දෛනික මතක් කිරීමේ දැනුම්දීමක් එකතු කරන්න.'],
            ['overall' => 5, 'ui' => 4, 'q' => 5, 'si' => 5, 'use' => 5, 'locale' => 'si', 'comment' => 'මාදිලි විභාගය සැබෑ විභාගයට සමාන හැඟීමක් ලබා දුන්නා.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 5, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Games are a fun way to warm up before a real practice session.', 'suggestion' => 'Add difficulty settings to Math Rush.'],
            ['overall' => 3, 'ui' => 3, 'q' => 3, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => 'Readiness percentage jumped around a lot week to week, not sure how stable it is.', 'suggestion' => 'Explain more clearly why the number changed.'],
            ['overall' => 2, 'ui' => 3, 'q' => 2, 'si' => null, 'use' => 2, 'locale' => 'en', 'comment' => 'Ran into a few duplicate-feeling questions in the reasoning category.', 'suggestion' => 'Audit for repeated question patterns.'],
            ['overall' => 5, 'ui' => 5, 'q' => 5, 'si' => null, 'use' => 5, 'locale' => 'en', 'comment' => 'The weak-area recommendations are spot on - it correctly identified my worst category.', 'suggestion' => null],
            ['overall' => 4, 'ui' => 3, 'q' => 4, 'si' => null, 'use' => 4, 'locale' => 'en', 'comment' => null, 'suggestion' => 'Would like a printable PDF of my study plan.'],
            ['overall' => 3, 'ui' => 4, 'q' => 3, 'si' => 3, 'use' => 3, 'locale' => 'si', 'comment' => 'සාමාන්‍යයෙන් හොඳයි, නමුත් ප්‍රශ්න ටිකක් අමාරුයි මගේ මට්ටමට වඩා.', 'suggestion' => null],
            ['overall' => 1, 'ui' => 1, 'q' => 2, 'si' => 1, 'use' => 1, 'locale' => 'si', 'comment' => 'යෙදුම නිතර හෙමින් ක්‍රියා කරයි.', 'suggestion' => 'කාර්යක්ෂමතාව වැඩිදියුණු කරන්න.'],
        ];

        foreach ($entries as $i => $entry) {
            $user = $demoUsers[$i % $demoUsers->count()];

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
                'is_demo_feedback' => true,
                'created_at' => Carbon::now()->subDays(random_int(0, 20)),
            ]);
        }
    }

    private function removeExisting(): void
    {
        $this->info('Removing existing demo data...');
        $demoUserIds = User::where('is_demo_user', true)->pluck('id');

        Feedback::where('is_demo_feedback', true)->delete();

        if ($demoUserIds->isNotEmpty()) {
            $sessionIds = TestSession::whereIn('user_id', $demoUserIds)->pluck('id');
            SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();
            TestSession::whereIn('user_id', $demoUserIds)->delete();
            GameScore::whereIn('user_id', $demoUserIds)->delete();
            UserDailyCheckin::whereIn('user_id', $demoUserIds)->delete();
            DB::table('user_progress_snapshots')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('exam_readiness_predictions')->whereIn('user_id', $demoUserIds)->delete();
            ExamProfile::whereIn('user_id', $demoUserIds)->delete();
            User::whereIn('id', $demoUserIds)->delete();
        }
    }
}

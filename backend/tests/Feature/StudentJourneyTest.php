<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** One student walks through every student-facing feature in the order a real user would, against the real dev database. */
class StudentJourneyTest extends TestCase
{
    private ?User $student = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The journey sends ~100 requests in seconds; a real student never does.
        $this->withoutMiddleware(ThrottleRequests::class);

        // Never reach Google from a test: the mock drivers give deterministic output.
        config(['services.ai_feedback_driver' => 'mock', 'services.ai_coach_driver' => 'mock']);
    }

    protected function tearDown(): void
    {
        if ($this->student) {
            $sessionIds = TestSession::where('user_id', $this->student->id)->pluck('id');
            SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();

            // Remove this user's rows from every table that references it, then the user.
            $tables = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);
            for ($pass = 0; $pass < 3; $pass++) {
                foreach ($tables as $table) {
                    if ($table !== 'users' && Schema::hasColumn($table, 'user_id')) {
                        try {
                            DB::table($table)->where('user_id', $this->student->id)->delete();
                        } catch (\Throwable) {
                            // a dependent table is cleared on a later pass
                        }
                    }
                }
            }
            $this->student->delete();
        }

        parent::tearDown();
    }

    private function as(): static
    {
        // Reload each time: earlier steps (placement, XP) change the row, as they would between real requests.
        // Guards cache the first resolved user for the life of the test app; real requests never share one.
        app('auth')->forgetGuards();

        return $this->actingAs(User::findOrFail($this->student->id), 'web');
    }

    private function answerEverything(int $sessionId, array $firstQuestion, bool $correct): void
    {
        $current = $firstQuestion;
        $guard = 0;

        while ($current !== null && $guard++ < 60) {
            $question = Question::find($current['id']);
            $keys = array_column($question->options, 'key');
            $wrong = collect($keys)->first(fn ($k) => $k !== $question->correct_option_key);

            $response = $this->as()->postJson("/api/sessions/{$sessionId}/answers", [
                'question_id' => $question->id,
                'selected_option_key' => $correct ? $question->correct_option_key : $wrong,
                'response_time_ms' => 9000,
            ]);
            $response->assertStatus(200);
            $response->assertJsonPath('data.is_correct', $correct);

            $current = $response->json('data.next_question') ?? $response->json('data.current_question');
        }
    }

    public function test_a_student_can_use_every_feature_end_to_end()
    {
        $this->student = User::create([
            'name' => 'Journey Student',
            'email' => 'journey-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ])->fresh();

        // Reference data the UI loads first.
        $categories = $this->as()->getJson('/api/categories')->assertOk()->json('data');
        $this->assertCount(5, $categories);
        $this->as()->getJson('/api/levels')->assertOk();

        // 1. Adaptive placement (mostly correct answers) sets a level.
        $placement = $this->as()->postJson('/api/sessions/placement/start')->assertStatus(201);
        $placementId = $placement->json('data.id');
        $current = $placement->json('data.current_question');
        $items = 0;
        while ($current !== null && $items++ < 40) {
            $q = Question::find($current['id']);
            $resp = $this->as()->postJson("/api/sessions/{$placementId}/answers", [
                'question_id' => $q->id,
                'selected_option_key' => $q->correct_option_key,
                'response_time_ms' => 8000,
            ])->assertOk();
            if ($resp->json('data.ready_to_complete')) {
                break;
            }
            $current = $resp->json('data.next_question');
        }
        $complete = $this->as()->postJson("/api/sessions/{$placementId}/complete")->assertOk();
        $this->assertNotNull($complete->json('data.score_percent'), 'Placement returns a result.');
        $fresh = $this->student->fresh();
        $this->assertNotNull($fresh->theta_estimate, 'Placement stores an ability estimate.');
        $this->assertNotNull($fresh->placement_completed_at);
        $this->assertNotNull($fresh->current_level_id, 'Placement assigns a level.');

        // 2. Category practice: answer, complete, read the report, ask for an explanation.
        $categoryId = $categories[0]['id'];
        $practice = $this->as()->postJson('/api/sessions/practice/start', ['category_id' => $categoryId, 'total_questions' => 5]);
        $this->assertSame(201, $practice->getStatusCode(), $practice->getContent());
        $practiceId = $practice->json('data.id');
        $questions = $practice->json('data.questions') ?? [];
        $this->assertNotEmpty($questions);

        $firstAnswerId = null;
        foreach ($questions as $i => $item) {
            $q = Question::find($item['id']);
            $wrongKey = collect(array_column($q->options, 'key'))->first(fn ($k) => $k !== $q->correct_option_key);
            $resp = $this->as()->postJson("/api/sessions/{$practiceId}/answers", [
                'question_id' => $q->id,
                'selected_option_key' => $i === 0 ? $wrongKey : $q->correct_option_key,
                'response_time_ms' => 12000,
            ])->assertOk();
            $firstAnswerId ??= $resp->json('data.answer_id') ?? $resp->json('data.id');
        }
        $this->as()->postJson("/api/sessions/{$practiceId}/complete")->assertOk();
        $report = $this->as()->getJson("/api/sessions/{$practiceId}/report")->assertOk();
        $this->assertNotNull($report->json('data'));
        $answerId = SessionAnswer::where('test_session_id', $practiceId)->where('is_correct', false)->value('id');
        $this->assertNotNull($answerId);
        $this->as()->postJson("/api/sessions/{$practiceId}/answers/{$answerId}/explain")->assertOk();

        // 3. Daily session, exam profile, study plan, timed mock exam.
        $this->as()->postJson('/api/sessions/daily/start')->assertStatus(201);
        $this->as()->postJson('/api/exam-profile', [
            'exam_name' => 'Journey Test Exam',
            'exam_date' => now()->addDays(45)->toDateString(),
            'daily_study_hours_target' => 2,
            'target_score' => 70,
        ])->assertSuccessful();
        $this->as()->getJson('/api/exam-profile')->assertOk();
        $plan = $this->as()->getJson('/api/exam-profile/study-plan')->assertOk()->json('data');
        $this->assertNotEmpty($plan['daily_plan'] ?? []);
        $this->as()->postJson('/api/mock-exams', ['total_questions' => 10, 'duration_minutes' => 15])->assertStatus(201);

        // 4. Every game accepts a score and reports a personal best.
        $games = $this->as()->getJson('/api/games')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(8, count($games));
        foreach ($games as $game) {
            $this->as()->postJson("/api/games/{$game['code']}/score", ['score' => 500, 'duration_seconds' => 60, 'metadata' => ['rounds' => 3]])->assertSuccessful();
            $this->as()->getJson("/api/games/{$game['code']}/scores/me")->assertOk();
        }

        // 5. Daily check-in, dashboard, gamification, coach, feedback.
        $this->as()->postJson('/api/checkins', ['study_hours' => 2, 'motivation_score' => 8, 'attended' => true])->assertSuccessful();
        $this->as()->getJson('/api/checkins/today')->assertOk();
        $summary = $this->as()->getJson('/api/dashboard/summary')->assertOk()->json('data');
        $this->assertNotEmpty($summary);
        $this->as()->getJson('/api/dashboard/progress-history')->assertOk();
        $this->as()->getJson('/api/gamification/summary')->assertOk();
        $this->as()->getJson('/api/gamification/badges')->assertOk();
        $this->as()->getJson('/api/gamification/leaderboard')->assertOk();
        $this->as()->getJson('/api/gamification/missions')->assertOk();
        $this->as()->postJson('/api/coach/chat', ['message' => 'Which game helps my memory?', 'history' => []])->assertOk();
        $this->as()->postJson('/api/feedback', ['overall_rating' => 5, 'comment' => 'Journey test'])->assertSuccessful();
        $this->as()->getJson('/api/feedback/mine')->assertOk();

        // 6. Study notes.
        $this->as()->getJson('/api/study-notes')->assertOk();
        $this->as()->getJson('/api/study-notes/due-today')->assertOk();
        $this->as()->getJson('/api/study-notes/recommendation')->assertOk();
    }
}

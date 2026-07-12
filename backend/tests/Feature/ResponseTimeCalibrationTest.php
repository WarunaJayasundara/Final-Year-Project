<?php

namespace Tests\Feature;

use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Services\Irt\ResponseTimeCalibrationService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises response_time_ms capture (TestSessionController::submitAnswer)
 * and the resulting uncalibrated -> provisional -> calibrated lifecycle
 * (ResponseTimeCalibrationService), against the real dev database (no
 * RefreshDatabase - see AdaptivePlacementTest), with explicit tearDown.
 */
class ResponseTimeCalibrationTest extends TestCase
{
    private ?User $testUser = null;

    private array $sessionIds = [];

    private ?int $targetQuestionId = null;

    private ?float $originalLearnedTime = null;

    private ?int $originalSampleCount = null;

    private ?string $originalCalibrationStatus = null;

    protected function tearDown(): void
    {
        if ($this->sessionIds) {
            SessionAnswer::whereIn('test_session_id', $this->sessionIds)->delete();
            TestSession::whereIn('id', $this->sessionIds)->delete();
        }
        if ($this->testUser) {
            $this->testUser->delete();
        }
        // Restore the shared question's calibration state so this test
        // never permanently mutates real bank data other tests might rely on.
        if ($this->targetQuestionId) {
            Question::whereKey($this->targetQuestionId)->update([
                'learned_expected_time_seconds' => $this->originalLearnedTime,
                'time_sample_count' => $this->originalSampleCount,
                'time_calibration_status' => $this->originalCalibrationStatus,
            ]);
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Response Time Test User',
            'email' => 'response-time-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    public function test_submit_answer_persists_response_time_and_computes_ratio()
    {
        $this->testUser = $this->makeUser();
        $level = IqLevel::where('level_number', 3)->firstOrFail();
        $question = Question::where('level_id', $level->id)->where('is_active', true)->firstOrFail();
        $question->update(['solving_time_seconds' => 30, 'learned_expected_time_seconds' => null]);

        $session = TestSession::create([
            'user_id' => $this->testUser->id,
            'session_type' => 'practice',
            'category_id' => $question->category_id,
            'level_id' => $level->id,
            'total_questions' => 1,
            'started_at' => now(),
        ]);
        $this->sessionIds[] = $session->id;
        SessionAnswer::create(['test_session_id' => $session->id, 'question_id' => $question->id, 'is_correct' => false]);

        // 15000ms = 15s against a 30s expected time -> ratio 0.5, well within tolerance.
        $response = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$session->id}/answers", [
            'question_id' => $question->id,
            'selected_option_key' => $question->correct_option_key,
            'response_time_ms' => 15000,
        ]);

        $response->assertStatus(200);

        $answer = SessionAnswer::where('test_session_id', $session->id)->where('question_id', $question->id)->first();
        $this->assertSame(15000, $answer->response_time_ms);
        $this->assertEqualsWithDelta(0.5, $answer->time_performance_ratio, 0.01);
        $this->assertTrue($answer->answered_within_expected_time);
    }

    public function test_submit_answer_flags_a_much_slower_than_expected_response()
    {
        $this->testUser = $this->makeUser();
        $level = IqLevel::where('level_number', 3)->firstOrFail();
        $question = Question::where('level_id', $level->id)->where('is_active', true)->skip(1)->firstOrFail();
        $question->update(['solving_time_seconds' => 20, 'learned_expected_time_seconds' => null]);

        $session = TestSession::create([
            'user_id' => $this->testUser->id,
            'session_type' => 'practice',
            'category_id' => $question->category_id,
            'level_id' => $level->id,
            'total_questions' => 1,
            'started_at' => now(),
        ]);
        $this->sessionIds[] = $session->id;
        SessionAnswer::create(['test_session_id' => $session->id, 'question_id' => $question->id, 'is_correct' => false]);

        // 60000ms = 60s against a 20s expected time -> ratio 3.0, well beyond tolerance.
        $response = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$session->id}/answers", [
            'question_id' => $question->id,
            'selected_option_key' => $question->correct_option_key,
            'response_time_ms' => 60000,
        ]);

        $response->assertStatus(200);

        $answer = SessionAnswer::where('test_session_id', $session->id)->where('question_id', $question->id)->first();
        $this->assertFalse($answer->answered_within_expected_time);
    }

    public function test_calibration_lifecycle_moves_from_uncalibrated_to_provisional_to_calibrated()
    {
        $this->testUser = $this->makeUser();
        $level = IqLevel::where('level_number', 3)->firstOrFail();
        $question = Question::where('level_id', $level->id)->where('is_active', true)->skip(2)->firstOrFail();
        $this->targetQuestionId = $question->id;
        $this->originalLearnedTime = $question->learned_expected_time_seconds;
        $this->originalSampleCount = $question->time_sample_count;
        $this->originalCalibrationStatus = $question->time_calibration_status;

        $question->update(['learned_expected_time_seconds' => null, 'time_sample_count' => 0, 'time_calibration_status' => 'uncalibrated']);

        // Fewer than PROVISIONAL_THRESHOLD (10) samples -> stays uncalibrated.
        $this->recordResponseTimes($question, 5, 20000);
        (new ResponseTimeCalibrationService())->calibrate();
        $question->refresh();
        $this->assertSame('uncalibrated', $question->time_calibration_status);
        $this->assertNull($question->learned_expected_time_seconds);

        // 10-29 samples -> provisional, with a real learned median.
        $this->recordResponseTimes($question, 10, 20000);
        (new ResponseTimeCalibrationService())->calibrate();
        $question->refresh();
        $this->assertSame('provisional', $question->time_calibration_status);
        $this->assertEqualsWithDelta(20.0, $question->learned_expected_time_seconds, 0.1);

        // 30+ samples -> calibrated.
        $this->recordResponseTimes($question, 20, 20000);
        (new ResponseTimeCalibrationService())->calibrate();
        $question->refresh();
        $this->assertSame('calibrated', $question->time_calibration_status);
    }

    private function recordResponseTimes(Question $question, int $count, int $responseTimeMs): void
    {
        $session = TestSession::create([
            'user_id' => $this->testUser->id,
            'session_type' => 'practice',
            'category_id' => $question->category_id,
            'level_id' => $question->level_id,
            'total_questions' => $count,
            'started_at' => now(),
        ]);
        $this->sessionIds[] = $session->id;

        for ($i = 0; $i < $count; $i++) {
            SessionAnswer::create([
                'test_session_id' => $session->id,
                // A distinct session per batch of responses keeps the unique
                // (test_session_id, question_id) constraint happy while still
                // recording $count independent samples for this question.
                'question_id' => $question->id,
                'selected_option_key' => $question->correct_option_key,
                'is_correct' => true,
                'answered_at' => now(),
                'response_time_ms' => $responseTimeMs,
            ]);

            if ($i < $count - 1) {
                $session = TestSession::create([
                    'user_id' => $this->testUser->id,
                    'session_type' => 'practice',
                    'category_id' => $question->category_id,
                    'level_id' => $question->level_id,
                    'total_questions' => 1,
                    'started_at' => now(),
                ]);
                $this->sessionIds[] = $session->id;
            }
        }
    }
}

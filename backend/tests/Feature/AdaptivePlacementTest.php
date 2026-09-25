<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Exercises the real sequential CAT placement flow end-to-end against the live app database. */
class AdaptivePlacementTest extends TestCase
{
    private ?User $testUser = null;

    protected function tearDown(): void
    {
        if ($this->testUser) {
            $sessionIds = TestSession::where('user_id', $this->testUser->id)->pluck('id');
            SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();
            TestSession::whereIn('id', $sessionIds)->delete();
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    public function test_high_performer_reaches_high_ability_and_level()
    {
        $this->testUser = User::create([
            'name' => 'IRT Test High Performer',
            'email' => 'irt-test-high-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/sessions/placement/start');
        $response->assertStatus(201);

        $sessionId = $response->json('data.id');
        $current = $response->json('data.current_question');
        $itemsSeen = 1;

        while ($current !== null && $itemsSeen < 40) {
            $question = Question::find($current['id']);
            $answer = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$sessionId}/answers", [
                'question_id' => $current['id'],
                'selected_option_key' => $question->correct_option_key,
            ]);

            $answer->assertStatus(200);

            if ($answer->json('data.ready_to_complete')) {
                break;
            }

            $current = $answer->json('data.next_question');
            $itemsSeen++;
        }

        $this->assertLessThan(40, $itemsSeen, 'Adaptive placement did not terminate within a sane item bound.');

        $complete = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$sessionId}/complete");
        $complete->assertStatus(200);

        $this->testUser->refresh();
        $this->assertNotNull($this->testUser->placement_completed_at);
        $this->assertNotNull($this->testUser->theta_estimate);
        $this->assertGreaterThan(0.0, $this->testUser->theta_estimate, 'Answering every item correctly should yield above-average ability.');
        $this->assertNotNull($this->testUser->current_level_id);
    }

    public function test_low_performer_reaches_low_ability_and_level()
    {
        $this->testUser = User::create([
            'name' => 'IRT Test Low Performer',
            'email' => 'irt-test-low-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/sessions/placement/start');
        $response->assertStatus(201);

        $sessionId = $response->json('data.id');
        $current = $response->json('data.current_question');
        $itemsSeen = 1;

        while ($current !== null && $itemsSeen < 40) {
            $question = Question::find($current['id']);
            $wrongKey = collect($question->options)->pluck('key')->first(fn ($key) => $key !== $question->correct_option_key);

            $answer = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$sessionId}/answers", [
                'question_id' => $current['id'],
                'selected_option_key' => $wrongKey,
            ]);

            $answer->assertStatus(200);

            if ($answer->json('data.ready_to_complete')) {
                break;
            }

            $current = $answer->json('data.next_question');
            $itemsSeen++;
        }

        $this->assertLessThan(40, $itemsSeen);

        $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$sessionId}/complete")->assertStatus(200);

        $this->testUser->refresh();
        $this->assertLessThan(0.0, $this->testUser->theta_estimate, 'Answering every item incorrectly should yield below-average ability.');
    }

    public function test_placement_session_enforces_minimum_item_stopping_rule()
    {
        $this->testUser = User::create([
            'name' => 'IRT Test Early Complete',
            'email' => 'irt-test-early-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/sessions/placement/start');
        $response->assertStatus(201);
        $sessionId = $response->json('data.id');

        // Answer only 3 items - well under PLACEMENT_MIN_ITEMS (15) - then try
        // to complete directly, bypassing the normal item-by-item flow.
        $current = $response->json('data.current_question');
        for ($i = 0; $i < 3 && $current !== null; $i++) {
            $question = Question::find($current['id']);
            $answer = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$sessionId}/answers", [
                'question_id' => $current['id'],
                'selected_option_key' => $question->correct_option_key,
            ]);
            $this->assertFalse($answer->json('data.ready_to_complete'), 'Should not be ready to complete this early.');
            $current = $answer->json('data.next_question');
        }

        $prematureComplete = $this->actingAs($this->testUser, 'web')->postJson("/api/sessions/{$sessionId}/complete");
        $prematureComplete->assertStatus(422);

        $this->testUser->refresh();
        $this->assertNull($this->testUser->placement_completed_at, 'A session below the minimum item count must not be allowed to finish.');
    }
}

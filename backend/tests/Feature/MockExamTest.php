<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IqLevel;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserProgressSnapshot;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the mock-exam generator (MockExamController + QuestionSamplingService::
 * sampleForMockExam()) against the real dev database (no RefreshDatabase -
 * see AdaptivePlacementTest), with explicit tearDown cleanup.
 */
class MockExamTest extends TestCase
{
    private ?User $testUser = null;

    private array $sessionIds = [];

    private array $snapshotIds = [];

    protected function tearDown(): void
    {
        if ($this->sessionIds) {
            SessionAnswer::whereIn('test_session_id', $this->sessionIds)->delete();
            TestSession::whereIn('id', $this->sessionIds)->delete();
        }
        if ($this->snapshotIds) {
            UserProgressSnapshot::whereIn('id', $this->snapshotIds)->delete();
        }
        if ($this->testUser) {
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Mock Exam Test User',
            'email' => 'mock-exam-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    private function setAccuracy(User $user, Category $category, float $accuracy): void
    {
        $snapshot = UserProgressSnapshot::create([
            'user_id' => $user->id,
            'snapshot_date' => now()->toDateString(),
            'level_id' => IqLevel::where('level_number', 3)->value('id'),
            'category_id' => $category->id,
            'accuracy_percent' => $accuracy,
            'questions_answered' => 10,
        ]);
        $this->snapshotIds[] = $snapshot->id;
    }

    public function test_requires_placement_completed_first()
    {
        $this->testUser = $this->makeUser();

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/mock-exams', []);

        $response->assertStatus(422);
    }

    public function test_default_mock_exam_is_created_with_time_limit()
    {
        $this->testUser = $this->makeUser();
        $this->testUser->update(['current_level_id' => IqLevel::where('level_number', 3)->value('id')]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/mock-exams', []);

        $response->assertStatus(201);
        $this->sessionIds[] = $response->json('data.id');

        $response->assertJsonPath('data.session_type', 'mock');
        $response->assertJsonPath('data.time_limit_seconds', 30 * 60);
        $this->assertCount(25, $response->json('data.questions'));
    }

    public function test_selected_categories_scope_only_includes_requested_categories()
    {
        $this->testUser = $this->makeUser();
        $this->testUser->update(['current_level_id' => IqLevel::where('level_number', 3)->value('id')]);
        $memory = Category::where('code', 'memory')->firstOrFail();
        $attention = Category::where('code', 'attention')->firstOrFail();

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/mock-exams', [
            'total_questions' => 20,
            'scope' => 'selected_categories',
            'category_ids' => [$memory->id, $attention->id],
        ]);

        $response->assertStatus(201);
        $this->sessionIds[] = $response->json('data.id');

        $categoryIds = collect($response->json('data.questions'))->pluck('category_id')->unique();
        $this->assertTrue($categoryIds->diff([$memory->id, $attention->id])->isEmpty());
    }

    public function test_weak_category_is_over_represented_relative_to_a_strong_one()
    {
        $this->testUser = $this->makeUser();
        $this->testUser->update(['current_level_id' => IqLevel::where('level_number', 3)->value('id')]);
        $weak = Category::where('code', 'numerical_ability')->firstOrFail();
        $strong = Category::where('code', 'spatial_pattern')->firstOrFail();
        $this->setAccuracy($this->testUser, $weak, 20.0);
        $this->setAccuracy($this->testUser, $strong, 90.0);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/mock-exams', [
            'total_questions' => 40,
            'scope' => 'selected_categories',
            'category_ids' => [$weak->id, $strong->id],
        ]);

        $response->assertStatus(201);
        $this->sessionIds[] = $response->json('data.id');

        $counts = collect($response->json('data.questions'))->countBy('category_id');
        $this->assertGreaterThan($counts->get($strong->id, 0), $counts->get($weak->id, 0),
            'The 20%-accuracy category should be allocated more mock-exam questions than the 90%-accuracy category.');
        // Every requested category still guaranteed a share - never starved to zero.
        $this->assertGreaterThan(0, $counts->get($strong->id, 0));
    }
}

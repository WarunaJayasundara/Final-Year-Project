<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Services\Sessions\WeakAreaWeightingService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises WeakAreaWeightingService against the real dev database (no
 * RefreshDatabase - see AdaptivePlacementTest), with explicit tearDown
 * cleanup of every row this test creates.
 */
class WeakAreaWeightingTest extends TestCase
{
    private ?User $testUser = null;

    private array $sessionIds = [];

    protected function tearDown(): void
    {
        if ($this->sessionIds) {
            SessionAnswer::whereIn('test_session_id', $this->sessionIds)->delete();
            TestSession::whereIn('id', $this->sessionIds)->delete();
        }
        if ($this->testUser) {
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Weak Area Test User',
            'email' => 'weak-area-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    /** Records $count answered responses for $user in $category, $correctCount of them correct. */
    private function recordAnswers(User $user, Category $category, int $count, int $correctCount): void
    {
        $level = IqLevel::where('level_number', 3)->firstOrFail();
        $questions = Question::where('category_id', $category->id)
            ->where('level_id', $level->id)
            ->where('is_active', true)
            ->limit($count)
            ->get();

        $this->assertGreaterThanOrEqual($count, $questions->count(), "Fixture requires at least {$count} active questions in category {$category->code}.");

        $session = TestSession::create([
            'user_id' => $user->id,
            'session_type' => 'daily',
            'level_id' => $level->id,
            'total_questions' => $count,
            'correct_count' => $correctCount,
            'score_percent' => $count > 0 ? round($correctCount / $count * 100, 2) : 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $this->sessionIds[] = $session->id;

        foreach ($questions as $i => $question) {
            SessionAnswer::create([
                'test_session_id' => $session->id,
                'question_id' => $question->id,
                'selected_option_key' => $question->correct_option_key,
                'is_correct' => $i < $correctCount,
                'answered_at' => now(),
            ]);
        }
    }

    public function test_a_brand_new_user_gets_an_even_split()
    {
        $this->testUser = $this->makeUser();
        $service = new WeakAreaWeightingService();

        $allocation = $service->allocationFor($this->testUser->id, 30);

        $this->assertCount(5, $allocation);
        $this->assertSame(30, array_sum($allocation));
        // No history at all -> every category should get the same (or near-same) share.
        $this->assertEqualsWithDelta(6, min($allocation), 1);
        $this->assertEqualsWithDelta(6, max($allocation), 1);
    }

    public function test_a_weak_category_receives_more_questions_than_a_strong_one()
    {
        $this->testUser = $this->makeUser();
        $weak = Category::where('code', 'numerical_ability')->firstOrFail();
        $strong = Category::where('code', 'memory')->firstOrFail();

        // 10 answers each, well above the min-sample-size floor: 20% vs 90% accuracy.
        $this->recordAnswers($this->testUser, $weak, 10, 2);
        $this->recordAnswers($this->testUser, $strong, 10, 9);

        $service = new WeakAreaWeightingService();
        $allocation = $service->allocationFor($this->testUser->id, 30);

        $this->assertSame(30, array_sum($allocation));
        $this->assertGreaterThan($allocation[$strong->id], $allocation[$weak->id],
            'The 20%-accuracy category should be allocated more questions than the 90%-accuracy category.');
    }

    public function test_no_category_is_starved_below_the_floor()
    {
        $this->testUser = $this->makeUser();
        $strong = Category::where('code', 'memory')->firstOrFail();

        // A near-perfect streak on one category should still leave it with
        // at least half of an even split, not zero.
        $this->recordAnswers($this->testUser, $strong, 10, 10);

        $service = new WeakAreaWeightingService();
        $allocation = $service->allocationFor($this->testUser->id, 30);

        $this->assertGreaterThanOrEqual(3, $allocation[$strong->id]); // half of the even share (6)
    }

    public function test_daily_session_start_applies_weighting_end_to_end()
    {
        $this->testUser = $this->makeUser();
        $weak = Category::where('code', 'logical_reasoning')->firstOrFail();
        $strong = Category::where('code', 'attention')->firstOrFail();
        $this->recordAnswers($this->testUser, $weak, 8, 1);
        $this->recordAnswers($this->testUser, $strong, 8, 8);
        $this->testUser->update(['current_level_id' => IqLevel::where('level_number', 3)->value('id')]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/sessions/daily/start', [
            'total_questions' => 30,
        ]);

        $response->assertStatus(201);
        $this->sessionIds[] = $response->json('data.id');

        $counts = collect($response->json('data.questions'))->countBy('category_id');
        $this->assertGreaterThan($counts->get($strong->id, 0), $counts->get($weak->id, 0),
            'The daily session should contain more questions from the weak category than the strong one.');
    }
}

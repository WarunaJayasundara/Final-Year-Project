<?php

namespace Tests\Feature;

use App\Models\ExamProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the exam-profile CRUD + rule-based study planner against the
 * real dev database (no RefreshDatabase in this project - see
 * AdaptivePlacementTest), with explicit tearDown cleanup.
 */
class ExamProfileTest extends TestCase
{
    private ?User $testUser = null;

    protected function tearDown(): void
    {
        if ($this->testUser) {
            ExamProfile::where('user_id', $this->testUser->id)->delete();
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    private function makeUser(string $tag): User
    {
        return User::create([
            'name' => 'Exam Profile Test User',
            'email' => "exam-profile-test-{$tag}-".uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    public function test_exam_categories_endpoint_returns_fixed_list()
    {
        $this->testUser = $this->makeUser('categories');

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/exam-profile/categories');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertContains('slas', $codes);
        $this->assertContains('grama_niladhari', $codes);
        $this->assertContains('other', $codes);
    }

    public function test_exam_profile_requires_name_and_date()
    {
        $this->testUser = $this->makeUser('invalid');

        // exam_category is no longer collected from the client at all (the
        // fixed-list dropdown was removed) - exam_name and exam_date are now
        // the required fields the freeform setup flow must provide.
        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/exam-profile', [
            'daily_study_hours_target' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['exam_name', 'exam_date']);
    }

    public function test_exam_profile_can_be_created_and_updated()
    {
        $this->testUser = $this->makeUser('crud');
        $examDate = Carbon::now()->addDays(45)->toDateString();

        $create = $this->actingAs($this->testUser, 'web')->postJson('/api/exam-profile', [
            'exam_name' => 'Sample Bank Officer Exam',
            'exam_date' => $examDate,
            'daily_study_hours_target' => 2.5,
            'target_score' => 75,
        ]);

        $create->assertStatus(200);
        // exam_category is no longer client-supplied - always stored as
        // 'other' (difficultyWeight()'s documented 1.0 default) now that the
        // fixed-list dropdown is gone.
        $create->assertJsonPath('data.exam_category', 'other');
        $create->assertJsonPath('data.exam_name', 'Sample Bank Officer Exam');
        $create->assertJsonPath('data.daily_study_hours_target', 2.5);
        $create->assertJsonPath('data.target_score', 75);
        $this->assertEquals(45, $create->json('data.days_remaining'));

        $this->assertEquals(1, ExamProfile::where('user_id', $this->testUser->id)->count());

        // Updating should upsert the same row, not create a second one.
        $newExamDate = Carbon::now()->addDays(60)->toDateString();
        $update = $this->actingAs($this->testUser, 'web')->postJson('/api/exam-profile', [
            'exam_name' => 'Updated Exam Name',
            'exam_date' => $newExamDate,
            'daily_study_hours_target' => 1.0,
        ]);

        $update->assertStatus(200);
        $update->assertJsonPath('data.exam_name', 'Updated Exam Name');
        $this->assertEquals(1, ExamProfile::where('user_id', $this->testUser->id)->count());
    }

    public function test_study_plan_defaults_when_no_exam_profile()
    {
        $this->testUser = $this->makeUser('no-profile');

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/exam-profile/study-plan');

        $response->assertStatus(200);
        $response->assertJsonPath('data.phase', 'foundation');
        $response->assertJsonPath('data.days_remaining', null);
        $response->assertJsonPath('data.weeks_remaining', null);
        $this->assertNotEmpty($response->json('data.daily_plan'));
        $this->assertCount(7, $response->json('data.weekly_schedule'));
    }

    public function test_study_plan_enters_final_revision_phase_when_exam_is_imminent()
    {
        $this->testUser = $this->makeUser('imminent');

        ExamProfile::create([
            'user_id' => $this->testUser->id,
            'exam_category' => 'slas',
            'exam_date' => Carbon::now()->addDays(5)->toDateString(),
            'daily_study_hours_target' => 2,
        ]);

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/exam-profile/study-plan');

        $response->assertStatus(200);
        $response->assertJsonPath('data.phase', 'final_revision');
        $response->assertJsonPath('data.days_remaining', 5);

        $timeline = $response->json('data.phase_timeline');
        $this->assertCount(1, $timeline, 'Only the final_revision phase should remain with 5 days left.');
        $this->assertTrue($timeline[0]['is_current']);

        // SLAS has a >1.0 difficulty weight, so its recommended weekly mock
        // tests should exceed the base final_revision value of 4.
        $this->assertGreaterThan(4, $response->json('data.recommended_weekly_mock_tests'));
    }

    public function test_study_plan_foundation_phase_for_distant_exam()
    {
        $this->testUser = $this->makeUser('distant');

        ExamProfile::create([
            'user_id' => $this->testUser->id,
            'exam_category' => 'grama_niladhari',
            'exam_date' => Carbon::now()->addDays(120)->toDateString(),
            'daily_study_hours_target' => 1.5,
        ]);

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/exam-profile/study-plan');

        $response->assertStatus(200);
        $response->assertJsonPath('data.phase', 'foundation');
        $this->assertCount(4, $response->json('data.phase_timeline'));
    }
}

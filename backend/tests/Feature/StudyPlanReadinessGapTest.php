<?php

namespace Tests\Feature;

use App\Models\ExamProfile;
use App\Models\ExamReadinessPrediction;
use App\Models\User;
use App\Services\Study\StudyPlanService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises StudyPlanService::generate()'s readiness_gap block (brief §10/§11:
 * intelligent countdown + "when the exam is near and the student is not
 * ready" warning), against the real dev database (no RefreshDatabase - see
 * AdaptivePlacementTest), with explicit tearDown.
 */
class StudyPlanReadinessGapTest extends TestCase
{
    private ?User $testUser = null;

    protected function tearDown(): void
    {
        if ($this->testUser) {
            ExamReadinessPrediction::where('user_id', $this->testUser->id)->delete();
            ExamProfile::where('user_id', $this->testUser->id)->delete();
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Readiness Gap Test User',
            'email' => 'readiness-gap-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    public function test_no_warning_when_no_exam_profile_exists()
    {
        $this->testUser = $this->makeUser();

        $plan = (new StudyPlanService())->generate($this->testUser);

        $this->assertNull($plan['readiness_gap']['warning']);
        $this->assertNull($plan['readiness_gap']['current_readiness_percent']);
    }

    public function test_no_warning_when_exam_is_far_away_even_with_a_large_gap()
    {
        $this->testUser = $this->makeUser();
        ExamProfile::create([
            'user_id' => $this->testUser->id,
            'exam_category' => 'other',
            'exam_name' => 'Distant Exam',
            'exam_date' => now()->addDays(90)->toDateString(),
            'daily_study_hours_target' => 0.5,
        ]);
        ExamReadinessPrediction::create([
            'user_id' => $this->testUser->id,
            'features' => [],
            'readiness_percent' => 20.0,
            'readiness_label' => 'high_risk',
            'reasons' => [],
            'model_version' => 'test',
            'predicted_at' => now(),
        ]);

        $plan = (new StudyPlanService())->generate($this->testUser);

        $this->assertNull($plan['readiness_gap']['warning'], 'Warning should not fire 90 days out even with low readiness.');
    }

    public function test_warning_fires_when_exam_is_near_readiness_is_low_and_plan_is_insufficient()
    {
        $this->testUser = $this->makeUser();
        ExamProfile::create([
            'user_id' => $this->testUser->id,
            'exam_category' => 'other',
            'exam_name' => 'Imminent Exam',
            'exam_date' => now()->addDays(10)->toDateString(),
            // Deliberately tiny daily budget so the required-vs-planned gap is real.
            'daily_study_hours_target' => 0.25,
        ]);
        ExamReadinessPrediction::create([
            'user_id' => $this->testUser->id,
            'features' => [],
            'readiness_percent' => 30.0,
            'readiness_label' => 'high_risk',
            'reasons' => [],
            'model_version' => 'test',
            'predicted_at' => now(),
        ]);

        $plan = (new StudyPlanService())->generate($this->testUser);
        $gap = $plan['readiness_gap'];

        $this->assertSame(30.0, $gap['current_readiness_percent']);
        $this->assertSame(80.0, $gap['target_readiness_percent']);
        $this->assertEqualsWithDelta(50.0, $gap['readiness_gap_points'], 0.1);
        $this->assertNotNull($gap['warning']);
        $this->assertSame('high', $gap['warning']['severity']);
        $this->assertGreaterThan(0, $gap['warning']['recommended_daily_minutes']);
        $this->assertStringContainsString('10 days', $gap['warning']['message_en']);
    }

    public function test_target_readiness_uses_pass_mark_when_set()
    {
        $this->testUser = $this->makeUser();
        ExamProfile::create([
            'user_id' => $this->testUser->id,
            'exam_category' => 'other',
            'exam_name' => 'Custom Pass Mark Exam',
            'exam_date' => now()->addDays(5)->toDateString(),
            'daily_study_hours_target' => 2.0,
            'pass_mark' => 65,
        ]);
        ExamReadinessPrediction::create([
            'user_id' => $this->testUser->id,
            'features' => [],
            'readiness_percent' => 50.0,
            'readiness_label' => 'needs_improvement',
            'reasons' => [],
            'model_version' => 'test',
            'predicted_at' => now(),
        ]);

        $plan = (new StudyPlanService())->generate($this->testUser);

        $this->assertSame(65.0, $plan['readiness_gap']['target_readiness_percent']);
    }
}

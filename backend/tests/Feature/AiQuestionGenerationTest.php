<?php

namespace Tests\Feature;

use App\Contracts\AiQuestionGeneratorServiceInterface;
use App\Models\AiGeneratedQuestion;
use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\User;
use App\Services\AiQuestionGeneration\QuestionDraftService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the AI question generation draft -> human-review -> promote
 * pipeline against the real dev database (no RefreshDatabase - see
 * AdaptivePlacementTest), with explicit tearDown cleanup. Uses the Mock
 * generator (default driver, no Gemini key required) so these tests never
 * depend on network access.
 */
class AiQuestionGenerationTest extends TestCase
{
    private ?User $adminUser = null;

    protected function tearDown(): void
    {
        if ($this->adminUser) {
            $draftIds = AiGeneratedQuestion::where('generated_by', $this->adminUser->id)->pluck('id');
            $promotedQuestionIds = AiGeneratedQuestion::whereIn('id', $draftIds)->whereNotNull('promoted_question_id')->pluck('promoted_question_id');
            Question::whereIn('id', $promotedQuestionIds)->delete();
            AiGeneratedQuestion::whereIn('id', $draftIds)->delete();
            $this->adminUser->delete();
        }

        parent::tearDown();
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'AI Question Test Admin',
            'email' => 'ai-question-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'admin',
            'locale' => 'en',
        ]);
    }

    public function test_admin_can_generate_pending_drafts()
    {
        $this->adminUser = $this->makeAdmin();
        $category = Category::where('code', 'numerical_ability')->firstOrFail();
        $level = IqLevel::where('level_number', 2)->firstOrFail();

        $response = $this->actingAs($this->adminUser, 'web')->postJson('/api/admin/ai-questions/generate', [
            'category_id' => $category->id,
            'level_id' => $level->id,
            'count' => 3,
        ]);

        $response->assertStatus(201);
        // The Mock generator's duplicate-guard can legitimately reject a
        // collision within a small batch (limited template variety), so
        // this asserts a tolerant range rather than an exact count.
        $count = count($response->json('data'));
        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertLessThanOrEqual(3, $count);

        foreach ($response->json('data') as $draft) {
            $this->assertEquals('pending', $draft['status']);
            $this->assertEquals('mock', $draft['source']);
            $this->assertContains($draft['correct_option_key'], ['A', 'B', 'C', 'D']);
            $this->assertCount(4, $draft['options']);
        }
    }

    public function test_non_admin_cannot_generate_drafts()
    {
        $student = User::create([
            'name' => 'Non Admin',
            'email' => 'non-admin-ai-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $category = Category::where('code', 'numerical_ability')->firstOrFail();
        $level = IqLevel::where('level_number', 2)->firstOrFail();

        $response = $this->actingAs($student, 'web')->postJson('/api/admin/ai-questions/generate', [
            'category_id' => $category->id,
            'level_id' => $level->id,
            'count' => 1,
        ]);

        $response->assertStatus(403);
        $student->delete();
    }

    public function test_approving_a_draft_promotes_it_to_a_live_question()
    {
        $this->adminUser = $this->makeAdmin();
        $category = Category::where('code', 'logical_reasoning')->firstOrFail();
        $level = IqLevel::where('level_number', 1)->firstOrFail();

        $generate = $this->actingAs($this->adminUser, 'web')->postJson('/api/admin/ai-questions/generate', [
            'category_id' => $category->id,
            'level_id' => $level->id,
            'count' => 1,
        ]);
        $draftId = $generate->json('data.0.id');

        $approve = $this->actingAs($this->adminUser, 'web')->postJson("/api/admin/ai-questions/{$draftId}/approve");
        $approve->assertStatus(200);
        $approve->assertJsonPath('data.draft.status', 'approved');

        $questionId = $approve->json('data.question.id');
        $question = Question::find($questionId);
        $this->assertNotNull($question);
        $this->assertTrue((bool) $question->is_active);
        $this->assertEquals($category->id, $question->category_id);

        // A second approve/reject attempt on an already-reviewed draft must be rejected.
        $this->actingAs($this->adminUser, 'web')->postJson("/api/admin/ai-questions/{$draftId}/approve")->assertStatus(422);
        $this->actingAs($this->adminUser, 'web')->postJson("/api/admin/ai-questions/{$draftId}/reject")->assertStatus(422);
    }

    public function test_rejecting_a_draft_does_not_create_a_live_question()
    {
        $this->adminUser = $this->makeAdmin();
        $category = Category::where('code', 'attention')->firstOrFail();
        $level = IqLevel::where('level_number', 3)->firstOrFail();

        $generate = $this->actingAs($this->adminUser, 'web')->postJson('/api/admin/ai-questions/generate', [
            'category_id' => $category->id,
            'level_id' => $level->id,
            'count' => 1,
        ]);
        $draftId = $generate->json('data.0.id');

        $reject = $this->actingAs($this->adminUser, 'web')->postJson("/api/admin/ai-questions/{$draftId}/reject");
        $reject->assertStatus(200);
        $reject->assertJsonPath('data.status', 'rejected');
        $this->assertNull(AiGeneratedQuestion::find($draftId)->promoted_question_id);
    }

    public function test_duplicate_generator_output_is_rejected_and_not_persisted()
    {
        $this->adminUser = $this->makeAdmin();
        $category = Category::where('code', 'numerical_ability')->firstOrFail();
        $level = IqLevel::where('level_number', 2)->firstOrFail();

        $existingQuestion = Question::where('category_id', $category->id)->first();
        $this->assertNotNull($existingQuestion, 'Fixture requires at least one seeded question in this category.');

        // A generator that always returns the exact text of an existing
        // question - QuestionDraftService's Jaccard duplicate check should
        // reject every attempt (retried 3x per requested question) and
        // therefore persist zero drafts, never a near-duplicate.
        $alwaysDuplicateGenerator = new class($existingQuestion->question_text_en) implements AiQuestionGeneratorServiceInterface {
            public function __construct(private string $duplicateText)
            {
            }

            public function generate($category, $level, $examCategoryLabel, array $avoidQuestionTexts): array
            {
                return [
                    'question_text_en' => $this->duplicateText,
                    'question_text_si' => $this->duplicateText,
                    'options' => [
                        ['key' => 'A', 'text_en' => '1', 'text_si' => '1'],
                        ['key' => 'B', 'text_en' => '2', 'text_si' => '2'],
                        ['key' => 'C', 'text_en' => '3', 'text_si' => '3'],
                        ['key' => 'D', 'text_en' => '4', 'text_si' => '4'],
                    ],
                    'correct_option_key' => 'A',
                    'explanation_en' => 'test',
                    'explanation_si' => 'test',
                    'difficulty_weight' => 2,
                ];
            }
        };

        $draftService = new QuestionDraftService($alwaysDuplicateGenerator);
        $countBefore = AiGeneratedQuestion::count();

        $created = $draftService->generateDrafts($category, $level, 2, null, $this->adminUser->id);

        $this->assertCount(0, $created, 'A generator that only produces duplicates of existing questions should yield zero persisted drafts.');
        $this->assertEquals($countBefore, AiGeneratedQuestion::count());
    }
}

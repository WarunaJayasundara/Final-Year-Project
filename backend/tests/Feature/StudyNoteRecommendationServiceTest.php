<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\SourceDocument;
use App\Models\StudyNote;
use App\Models\TestSession;
use App\Models\User;
use App\Services\Analytics\StudyNoteRecommendationService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises StudyNoteRecommendationService's weak-SUBCATEGORY-to-note
 * matching (brief §10: "you struggled with X - learn it now"), against the
 * real dev database (no RefreshDatabase), with explicit tearDown. Uses a
 * dedicated test-only subcategory/question/note trio rather than real bank
 * content, to stay isolated from production data.
 */
class StudyNoteRecommendationServiceTest extends TestCase
{
    private const TEST_SUBCATEGORY = 'recommendation_test_subcategory';

    private ?User $testUser = null;

    private array $testQuestionIds = [];

    private ?SourceDocument $testDocument = null;

    private ?StudyNote $testNote = null;

    private array $sessionIds = [];

    protected function tearDown(): void
    {
        if ($this->sessionIds) {
            SessionAnswer::whereIn('test_session_id', $this->sessionIds)->delete();
            TestSession::whereIn('id', $this->sessionIds)->delete();
        }
        $this->testNote?->delete();
        $this->testDocument?->delete();
        if ($this->testQuestionIds) {
            Question::whereIn('id', $this->testQuestionIds)->delete();
        }
        $this->testUser?->delete();

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Recommendation Test User',
            'email' => 'recommendation-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    public function test_returns_null_when_no_subcategory_is_weak_enough()
    {
        $this->testUser = $this->makeUser();

        $result = (new StudyNoteRecommendationService())->recommendFor($this->testUser);

        $this->assertNull($result);
    }

    public function test_recommends_the_published_note_matching_the_weakest_subcategory()
    {
        $this->testUser = $this->makeUser();
        $category = Category::first();
        $level = IqLevel::where('level_number', 3)->firstOrFail();

        // session_answers has a unique (test_session_id, question_id)
        // constraint - 10 distinct questions needed, not one reused 10x.
        $questions = [];
        for ($i = 0; $i < 10; $i++) {
            $questions[] = Question::create([
                'category_id' => $category->id,
                'level_id' => $level->id,
                'question_type' => 'mcq_text',
                'subcategory' => self::TEST_SUBCATEGORY,
                'question_text_en' => "Recommendation test question {$i}?",
                'question_text_si' => 'පරීක්ෂණ ප්‍රශ්නය?',
                'options' => [['key' => 'A', 'text_en' => 'A', 'text_si' => 'A'], ['key' => 'B', 'text_en' => 'B', 'text_si' => 'B']],
                'correct_option_key' => 'A',
                'difficulty_weight' => 3,
                'is_active' => true,
            ]);
        }
        $this->testQuestionIds = array_map(fn ($q) => $q->id, $questions);

        $this->testDocument = SourceDocument::create([
            'title' => 'Recommendation Test Doc',
            'document_type' => 'theory_book',
            'uploaded_by' => $this->testUser->id,
            'file_path' => 'source_documents/test.pdf',
            'analysis_status' => 'analyzed',
        ]);

        $this->testNote = StudyNote::create([
            'source_document_id' => $this->testDocument->id,
            'subcategory' => self::TEST_SUBCATEGORY,
            'title_en' => 'Recommendation Test Note',
            'title_si' => 'පරීක්ෂණ සටහන',
            'content_en' => 'Content.',
            'content_si' => 'අන්තර්ගතය.',
            'status' => 'published',
            'reviewed_at' => now(),
        ]);

        // 10 answers, 2 correct -> 20% accuracy, well below the weak threshold
        // and well above the min-sample-size floor.
        $session = TestSession::create([
            'user_id' => $this->testUser->id,
            'session_type' => 'daily',
            'level_id' => $level->id,
            'total_questions' => 10,
            'correct_count' => 2,
            'score_percent' => 20.0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        $this->sessionIds[] = $session->id;

        foreach ($this->testQuestionIds as $i => $questionId) {
            SessionAnswer::create([
                'test_session_id' => $session->id,
                'question_id' => $questionId,
                'selected_option_key' => $i < 2 ? 'A' : 'B',
                'is_correct' => $i < 2,
                'answered_at' => now(),
            ]);
        }

        $result = (new StudyNoteRecommendationService())->recommendFor($this->testUser);

        $this->assertNotNull($result);
        $this->assertSame(self::TEST_SUBCATEGORY, $result['subcategory']);
        $this->assertSame(20.0, $result['accuracy']);
        $this->assertSame($this->testNote->id, $result['study_note']->id);
    }
}

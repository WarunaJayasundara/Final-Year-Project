<?php

namespace Tests\Feature;

use App\Models\SourceDocument;
use App\Models\StudyNote;
use App\Models\StudyNoteReview;
use App\Models\User;
use App\Services\Analytics\SpacedRepetitionService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the simplified SM-2 spaced-repetition scheduler (brief §9:
 * "schedule weak concepts for future revision") against the real dev
 * database (no RefreshDatabase), with explicit tearDown.
 */
class SpacedRepetitionServiceTest extends TestCase
{
    private ?User $testUser = null;

    private ?SourceDocument $testDocument = null;

    private ?StudyNote $testNote = null;

    protected function tearDown(): void
    {
        if ($this->testUser) {
            StudyNoteReview::where('user_id', $this->testUser->id)->delete();
            $this->testUser->delete();
        }
        $this->testNote?->delete();
        $this->testDocument?->delete();

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Spaced Repetition Test User',
            'email' => 'spaced-rep-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    private function makeNote(): StudyNote
    {
        $this->testDocument = SourceDocument::create([
            'title' => 'Spaced Repetition Test Doc',
            'document_type' => 'theory_book',
            'uploaded_by' => $this->testUser->id,
            'file_path' => 'source_documents/test.pdf',
            'analysis_status' => 'analyzed',
        ]);

        return StudyNote::create([
            'source_document_id' => $this->testDocument->id,
            'subcategory' => 'test_subcategory',
            'title_en' => 'Test Note',
            'title_si' => 'පරීක්ෂණ සටහන',
            'content_en' => 'Test content.',
            'content_si' => 'පරීක්ෂණ අන්තර්ගතය.',
            'status' => 'published',
        ]);
    }

    public function test_first_review_creates_a_schedule_with_default_ease()
    {
        $this->testUser = $this->makeUser();
        $this->testNote = $this->makeNote();

        $review = (new SpacedRepetitionService())->schedule($this->testUser, $this->testNote);

        $this->assertSame(2.5, $review->ease_factor);
        $this->assertSame(1, $review->interval_days);
        $this->assertSame(0, $review->review_count);
    }

    public function test_again_result_shrinks_ease_and_resets_interval_to_one_day()
    {
        $this->testUser = $this->makeUser();
        $this->testNote = $this->makeNote();
        $service = new SpacedRepetitionService();

        // Build up some history first with 'good' results.
        $service->recordResult($this->testUser, $this->testNote, 'good');
        $service->recordResult($this->testUser, $this->testNote, 'good');

        $review = $service->recordResult($this->testUser, $this->testNote, 'again');

        $this->assertSame(1, $review->interval_days);
        $this->assertLessThan(2.5, $review->ease_factor);
        $this->assertSame('again', $review->last_result);
    }

    public function test_interval_grows_across_consecutive_good_results()
    {
        $this->testUser = $this->makeUser();
        $this->testNote = $this->makeNote();
        $service = new SpacedRepetitionService();

        $r1 = $service->recordResult($this->testUser, $this->testNote, 'good');
        $r2 = $service->recordResult($this->testUser, $this->testNote, 'good');
        $r3 = $service->recordResult($this->testUser, $this->testNote, 'good');

        $this->assertSame(1, $r1->interval_days);
        $this->assertSame(3, $r2->interval_days);
        $this->assertGreaterThan($r2->interval_days, $r3->interval_days);
    }

    public function test_due_today_only_returns_published_notes_due_on_or_before_today()
    {
        $this->testUser = $this->makeUser();
        $this->testNote = $this->makeNote();
        $service = new SpacedRepetitionService();

        StudyNoteReview::create([
            'user_id' => $this->testUser->id,
            'study_note_id' => $this->testNote->id,
            'ease_factor' => 2.5,
            'interval_days' => 1,
            'review_count' => 0,
            'next_review_at' => now()->toDateString(),
        ]);

        $due = $service->dueToday($this->testUser);

        $this->assertCount(1, $due);
        $this->assertSame($this->testNote->id, $due->first()->study_note_id);
    }
}

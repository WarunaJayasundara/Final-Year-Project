<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quality gate for the competitive question bank. Read-only assertions
 * against the seeded dev database (this suite has no RefreshDatabase - see
 * AdaptivePlacementTest), so it doubles as a post-seed integrity check:
 * volume, full category x level coverage, bilingual completeness,
 * option/answer-key validity, duplicate freedom, and image-asset existence.
 */
class QuestionBankTest extends TestCase
{
    public function test_bank_has_competitive_volume()
    {
        $this->assertGreaterThanOrEqual(5000, Question::where('is_active', true)->count());
    }

    public function test_every_category_level_cell_is_populated()
    {
        $cells = Question::where('is_active', true)
            ->selectRaw('category_id, level_id, count(*) as n')
            ->groupBy('category_id', 'level_id')
            ->get();

        $this->assertCount(25, $cells, 'Expected all 5 categories x 5 levels to have questions.');
        foreach ($cells as $cell) {
            $this->assertGreaterThanOrEqual(50, $cell->n, "Cell category {$cell->category_id} level {$cell->level_id} is too thin ({$cell->n}).");
        }
    }

    public function test_no_duplicate_questions()
    {
        $dupTexts = Question::where('is_active', true)->where('question_type', 'mcq_text')
            ->selectRaw('question_text_en')->groupBy('question_text_en')->havingRaw('count(*) > 1')->get();
        $this->assertCount(0, $dupTexts, 'Duplicate active text questions found.');

        $dupImages = Question::where('is_active', true)->where('question_type', 'mcq_image')
            ->selectRaw('image_path')->groupBy('image_path')->havingRaw('count(*) > 1')->get();
        $this->assertCount(0, $dupImages, 'Duplicate active image questions found.');
    }

    public function test_bank_includes_a_large_image_based_layer()
    {
        $this->assertGreaterThanOrEqual(1000, Question::where('is_active', true)->where('question_type', 'mcq_image')->count());
    }

    public function test_questions_are_structurally_valid_and_bilingual()
    {
        // A deterministic sample spread across the whole bank keeps this
        // fast while still touching every seeder's output over time.
        $sample = Question::where('is_active', true)->inRandomOrder()->limit(300)->get();

        foreach ($sample as $q) {
            $keys = collect($q->options)->pluck('key');
            $this->assertGreaterThanOrEqual(4, $keys->count(), "Question {$q->id} has fewer than 4 options.");
            $this->assertEquals($keys->count(), $keys->unique()->count(), "Question {$q->id} has duplicate option keys.");
            $this->assertContains($q->correct_option_key, $keys->all(), "Question {$q->id} correct key not among options.");
            $this->assertNotSame('', trim($q->question_text_en), "Question {$q->id} missing English text.");
            $this->assertNotSame('', trim((string) $q->question_text_si), "Question {$q->id} missing Sinhala text.");
        }
    }

    public function test_image_questions_have_existing_assets()
    {
        $sample = Question::where('is_active', true)
            ->where('question_type', 'mcq_image')
            ->inRandomOrder()->limit(60)->get();

        $this->assertNotEmpty($sample);
        foreach ($sample as $q) {
            $this->assertNotNull($q->image_path, "Image question {$q->id} has no image_path.");
            $this->assertTrue(Storage::disk('public')->exists($q->image_path), "Missing SVG for question {$q->id}: {$q->image_path}");
        }
    }

    public function test_bank_carries_competitive_metadata()
    {
        $subcategories = Question::where('is_active', true)->whereNotNull('subcategory')->distinct()->count('subcategory');
        $this->assertGreaterThanOrEqual(25, $subcategories, 'Expected a rich subcategory taxonomy.');

        $tagged = Question::where('is_active', true)->whereNotNull('exam_tags')->count();
        $total = Question::where('is_active', true)->count();
        $this->assertGreaterThanOrEqual(0.95, $tagged / $total, 'Most questions should carry government-exam tags.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Question;
use Database\Seeders\Questions\BuildsQuestions;
use Tests\TestCase;

/**
 * Regression test for the adult-content difficulty audit's fix to
 * BuildsQuestions::insertRows() - previously capped difficulty_weight to
 * max(1,min(3,ceil(level/2))), only 3 distinct values across 5 IQ levels,
 * so Level 5 content wasn't reliably flagged harder than Level 3. Confirms
 * the default now tracks level_number directly (1-5).
 *
 * insertRows()/options() are private on the BuildsQuestions trait and
 * "use"-ing it directly on the test class collides with Laravel's own
 * public TestCase::options() (HTTP OPTIONS request helper) - routed
 * through a tiny throwaway harness class instead.
 */
class DifficultyWeightFixTest extends TestCase
{
    private array $createdIds = [];

    protected function tearDown(): void
    {
        if ($this->createdIds) {
            Question::whereIn('id', $this->createdIds)->delete();
        }

        parent::tearDown();
    }

    public function test_default_difficulty_weight_tracks_level_number_one_to_five()
    {
        $category = Category::first();
        $rows = [];
        foreach ([1, 2, 3, 4, 5] as $level) {
            $rows[] = [
                $level, 'mcq_text',
                "Difficulty fix test EN {$level} ".uniqid(),
                "Difficulty fix test SI {$level}",
                [['key' => 'A', 'text_en' => 'A', 'text_si' => 'A'], ['key' => 'B', 'text_en' => 'B', 'text_si' => 'B']],
                'A', 'Explanation.', 'පැහැදිලි කිරීම.',
            ];
        }

        (new DifficultyWeightFixTestHarness())->callInsertRows($category->code, $rows);

        $inserted = Question::where('question_text_en', 'like', 'Difficulty fix test EN%')
            ->orderBy('id')
            ->get();
        $this->createdIds = $inserted->pluck('id')->all();

        $this->assertCount(5, $inserted);
        $this->assertSame([1, 2, 3, 4, 5], $inserted->pluck('difficulty_weight')->all());
    }

    public function test_explicit_difficulty_weight_override_is_respected()
    {
        $category = Category::first();
        $rows = [[
            5, 'mcq_text',
            'Difficulty fix override test '.uniqid(), 'පරීක්ෂණ',
            [['key' => 'A', 'text_en' => 'A', 'text_si' => 'A'], ['key' => 'B', 'text_en' => 'B', 'text_si' => 'B']],
            'A', 'Explanation.', 'පැහැදිලි කිරීම.',
            2, // explicit override - should win over the level-based default
        ]];

        (new DifficultyWeightFixTestHarness())->callInsertRows($category->code, $rows);

        $inserted = Question::where('question_text_en', 'like', 'Difficulty fix override test%')->first();
        $this->createdIds = [$inserted->id];

        $this->assertSame(2, $inserted->difficulty_weight);
    }
}

class DifficultyWeightFixTestHarness
{
    use BuildsQuestions {
        insertRows as public callInsertRows;
    }
}

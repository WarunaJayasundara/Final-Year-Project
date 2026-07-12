<?php

namespace Tests\Unit;

use App\Services\Analytics\SpeedAccuracyScoreService;
use PHPUnit\Framework\TestCase;

class SpeedAccuracyScoreServiceTest extends TestCase
{
    public function test_wrong_answers_always_score_zero_contribution(): void
    {
        $result = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => false, 'time_performance_ratio' => 0.1, 'difficulty_weight' => 3],
            ['is_correct' => false, 'time_performance_ratio' => 5.0, 'difficulty_weight' => 3],
        ]);

        $this->assertSame(0.0, $result['score']);
    }

    public function test_faster_correct_answer_never_scores_below_on_pace_correct_answer(): void
    {
        $onPace = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => true, 'time_performance_ratio' => 1.0, 'difficulty_weight' => 3],
        ]);
        $fast = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => true, 'time_performance_ratio' => 0.5, 'difficulty_weight' => 3],
        ]);
        $slow = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => true, 'time_performance_ratio' => 3.0, 'difficulty_weight' => 3],
        ]);

        $this->assertGreaterThanOrEqual($onPace['score'], $fast['score']);
        $this->assertLessThanOrEqual($onPace['score'], $slow['score']);
    }

    public function test_speed_influence_never_exceeds_documented_band(): void
    {
        $best = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => true, 'time_performance_ratio' => 0.0, 'difficulty_weight' => 1],
        ]);
        $worst = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => true, 'time_performance_ratio' => 100.0, 'difficulty_weight' => 1],
        ]);

        $this->assertEqualsWithDelta(100.0, $best['score'], 0.1);
        $this->assertEqualsWithDelta(85.0, $worst['score'], 0.1);
    }

    public function test_wrong_answer_answered_much_faster_than_expected_is_flagged_as_guess(): void
    {
        $result = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => false, 'time_performance_ratio' => 0.1, 'difficulty_weight' => 1],
            ['is_correct' => false, 'time_performance_ratio' => 1.0, 'difficulty_weight' => 1],
        ]);

        $this->assertSame(0.5, $result['guess_rate']);
    }

    public function test_empty_input_returns_null(): void
    {
        $this->assertNull(SpeedAccuracyScoreService::scoreForItems([]));
    }

    public function test_harder_correct_items_are_weighted_more_than_easier_wrong_items(): void
    {
        $result = SpeedAccuracyScoreService::scoreForItems([
            ['is_correct' => true, 'time_performance_ratio' => 1.0, 'difficulty_weight' => 5],
            ['is_correct' => false, 'time_performance_ratio' => 1.0, 'difficulty_weight' => 1],
        ]);

        // Weighted average of [100 * w5, 0 * w1] / (5+1) = 500/6 = 83.33
        $this->assertEqualsWithDelta(83.3, $result['score'], 0.1);
    }
}

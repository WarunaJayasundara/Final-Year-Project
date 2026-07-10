<?php

namespace Tests\Unit\Services;

use App\Services\Irt\RaschMath;
use PHPUnit\Framework\TestCase;

class RaschMathTest extends TestCase
{
    public function test_probability_correct_is_half_when_theta_equals_difficulty()
    {
        $this->assertEqualsWithDelta(0.5, RaschMath::probabilityCorrect(0.0, 0.0), 0.0001);
        $this->assertEqualsWithDelta(0.5, RaschMath::probabilityCorrect(1.5, 1.5), 0.0001);
    }

    public function test_probability_correct_increases_with_higher_ability()
    {
        $low = RaschMath::probabilityCorrect(-1.0, 0.0);
        $high = RaschMath::probabilityCorrect(1.0, 0.0);

        $this->assertGreaterThan($low, $high);
    }

    public function test_estimate_ability_recovers_high_theta_for_mostly_correct_responses()
    {
        $itemDifficulties = ['q1' => -1.0, 'q2' => 0.0, 'q3' => 1.0, 'q4' => 2.0, 'q5' => 3.0];
        $responses = ['q1' => true, 'q2' => true, 'q3' => true, 'q4' => true, 'q5' => false];

        $result = RaschMath::estimateAbility($itemDifficulties, $responses);

        // Got everything right except the hardest item (b=3) - ability should land
        // clearly above the difficulty of the items answered correctly.
        $this->assertGreaterThan(1.0, $result['theta']);
        $this->assertLessThan(4.5, $result['theta']);
        $this->assertEquals(5, $result['items_used']);
    }

    public function test_estimate_ability_recovers_low_theta_for_mostly_incorrect_responses()
    {
        $itemDifficulties = ['q1' => -3.0, 'q2' => -2.0, 'q3' => -1.0, 'q4' => 0.0, 'q5' => 1.0];
        $responses = ['q1' => true, 'q2' => false, 'q3' => false, 'q4' => false, 'q5' => false];

        $result = RaschMath::estimateAbility($itemDifficulties, $responses);

        $this->assertLessThan(-1.0, $result['theta']);
    }

    public function test_estimate_ability_handles_no_matching_items()
    {
        $result = RaschMath::estimateAbility(['q1' => 0.0], ['q2' => true]);

        $this->assertSame(0.0, $result['theta']);
        $this->assertSame(0, $result['items_used']);
    }

    public function test_calibrate_items_recovers_relative_item_ordering()
    {
        // Three items of clearly different true difficulty, answered by a modest
        // spread of simulated persons of varying ability - PROX should recover them
        // in the correct relative order (easy < medium < hard), which is the
        // property the adaptive item-selection logic actually depends on.
        mt_srand(42);
        $trueDifficulty = ['easy' => -1.5, 'medium' => 0.0, 'hard' => 1.5];
        $responses = [];

        foreach (range(1, 60) as $personIndex) {
            $theta = -2.0 + ($personIndex / 60) * 4.0; // spread abilities from -2 to +2
            foreach ($trueDifficulty as $item => $b) {
                $p = RaschMath::probabilityCorrect($theta, $b);
                $responses[] = [
                    'person' => $personIndex,
                    'item' => $item,
                    'correct' => (mt_rand() / mt_getrandmax()) < $p,
                ];
            }
        }

        $result = RaschMath::calibrateItems($responses);
        $recovered = $result['item_difficulty'];

        $this->assertLessThan($recovered['medium'], $recovered['easy']);
        $this->assertLessThan($recovered['hard'], $recovered['medium']);
    }

    public function test_calibrate_items_handles_perfect_and_zero_scores_without_infinity()
    {
        $responses = [
            ['person' => 1, 'item' => 'always_right', 'correct' => true],
            ['person' => 2, 'item' => 'always_right', 'correct' => true],
            ['person' => 1, 'item' => 'always_wrong', 'correct' => false],
            ['person' => 2, 'item' => 'always_wrong', 'correct' => false],
        ];

        $result = RaschMath::calibrateItems($responses);

        $this->assertIsFloat($result['item_difficulty']['always_right']);
        $this->assertIsFloat($result['item_difficulty']['always_wrong']);
        $this->assertLessThan($result['item_difficulty']['always_wrong'], $result['item_difficulty']['always_right']);
    }
}

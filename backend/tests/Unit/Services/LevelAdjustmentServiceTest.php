<?php

namespace Tests\Unit\Services;

use App\Services\Irt\AbilityEstimationService;
use App\Services\Leveling\LevelAdjustmentService;
use PHPUnit\Framework\TestCase;

class LevelAdjustmentServiceTest extends TestCase
{
    private LevelAdjustmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LevelAdjustmentService(new AbilityEstimationService());
    }

    /** @dataProvider thetaProvider */
    public function test_theta_maps_to_expected_level(float $theta, int $expectedLevel)
    {
        $this->assertSame($expectedLevel, $this->service->levelNumberForTheta($theta));
    }

    public static function thetaProvider(): array
    {
        return [
            'far below average' => [-3.0, 1],
            'just below level 1/2 cut' => [-2.1, 1],
            'just above level 1/2 cut' => [-1.9, 2],
            'just below level 2/3 cut' => [-1.1, 2],
            'just above level 2/3 cut' => [-0.9, 3],
            'exactly average' => [0.0, 3],
            'just below level 3/4 cut' => [0.9, 3],
            'just above level 3/4 cut' => [1.1, 4],
            'just below level 4/5 cut' => [1.9, 4],
            'just above level 4/5 cut' => [2.1, 5],
            'far above average' => [3.0, 5],
        ];
    }

    public function test_cutpoints_are_symmetric_around_zero()
    {
        $this->assertSame(3, $this->service->levelNumberForTheta(0.0));
        // 1.5 is deliberately not on a cutpoint itself, avoiding the
        // strict-less-than boundary asymmetry that exact cutpoint values
        // (1.0, 2.0, ...) would introduce into this check.
        $this->assertSame($this->service->levelNumberForTheta(-1.5), 6 - $this->service->levelNumberForTheta(1.5));
    }

    /**
     * Levels must correspond 1:1 with IqScoreService::classify()'s IQ bands
     * on the same theta scale - the whole point of the realigned cutpoints.
     */
    public function test_levels_agree_with_iq_classification_bands()
    {
        $cases = [
            [-3.0, 1, 'extremely_low'],
            [-1.5, 2, 'below_average'],
            [0.0, 3, 'average'],
            [1.5, 4, 'above_average'],
            [3.0, 5, 'gifted'],
        ];

        foreach ($cases as [$theta, $expectedLevel, $expectedClassification]) {
            $iq = \App\Services\Analytics\IqScoreService::fromTheta($theta);
            $this->assertSame($expectedLevel, $this->service->levelNumberForTheta($theta));
            $this->assertSame($expectedClassification, \App\Services\Analytics\IqScoreService::classify($iq));
        }
    }
}

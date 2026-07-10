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
            'just below level 1/2 cut' => [-1.6, 1],
            'just above level 1/2 cut' => [-1.4, 2],
            'just below level 2/3 cut' => [-0.6, 2],
            'just above level 2/3 cut' => [-0.4, 3],
            'exactly average' => [0.0, 3],
            'just below level 3/4 cut' => [0.4, 3],
            'just above level 3/4 cut' => [0.6, 4],
            'just below level 4/5 cut' => [1.4, 4],
            'just above level 4/5 cut' => [1.6, 5],
            'far above average' => [3.0, 5],
        ];
    }

    public function test_cutpoints_are_symmetric_around_zero()
    {
        $this->assertSame(3, $this->service->levelNumberForTheta(0.0));
        $this->assertSame($this->service->levelNumberForTheta(-1.0), 6 - $this->service->levelNumberForTheta(1.0));
    }
}

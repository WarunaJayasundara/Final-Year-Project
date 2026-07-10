<?php

namespace Tests\Unit\Services;

use App\Services\Gamification\GamificationService;
use PHPUnit\Framework\TestCase;

class GamificationServiceTest extends TestCase
{
    private GamificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GamificationService();
    }

    public function test_xp_for_level_follows_triangular_curve()
    {
        $this->assertEquals(0, $this->service->xpForLevel(1));
        $this->assertEquals(100, $this->service->xpForLevel(2));
        $this->assertEquals(300, $this->service->xpForLevel(3));
        $this->assertEquals(600, $this->service->xpForLevel(4));
        $this->assertEquals(1000, $this->service->xpForLevel(5));
    }

    /** @dataProvider levelForXpProvider */
    public function test_level_for_xp(int $xp, int $expectedLevel)
    {
        $this->assertEquals($expectedLevel, $this->service->levelForXp($xp));
    }

    public static function levelForXpProvider(): array
    {
        return [
            'zero xp is level 1' => [0, 1],
            'just under level 2 threshold' => [99, 1],
            'exactly at level 2 threshold' => [100, 2],
            'mid level 2' => [150, 2],
            'exactly at level 3 threshold' => [300, 3],
            'far into level 5' => [1200, 5],
        ];
    }

    public function test_level_title_falls_back_to_generic_label_beyond_catalog()
    {
        $this->assertEquals('Novice', $this->service->levelTitle(1));
        $this->assertEquals('Level 99', $this->service->levelTitle(99));
    }

    public function test_summary_computes_progress_within_current_level()
    {
        $user = new \App\Models\User(['xp' => 150, 'coins' => 10]);

        $summary = $this->service->summary($user);

        $this->assertEquals(2, $summary['level']);
        $this->assertEquals(50, $summary['xp_into_level']); // 150 - xpForLevel(2)=100
        $this->assertEquals(200, $summary['xp_for_next_level']); // xpForLevel(3)=300 - 100
        $this->assertEquals(25.0, $summary['progress_percent']); // 50/200
    }

    public function test_session_rewards_scale_with_score_and_placement_bonus()
    {
        $daily = new \App\Models\TestSession(['session_type' => 'daily', 'score_percent' => 80]);
        [$xp, $coins] = $this->service->sessionRewards($daily);
        $this->assertEquals(50, $xp); // 10 + round(80*0.5)
        $this->assertEquals(8, $coins); // round(80/10)

        $placement = new \App\Models\TestSession(['session_type' => 'placement', 'score_percent' => 80]);
        [$placementXp, $placementCoins] = $this->service->sessionRewards($placement);
        $this->assertEquals(150, $placementXp); // 50 + 100 bonus
        $this->assertEquals(58, $placementCoins); // 8 + 50 bonus
    }
}

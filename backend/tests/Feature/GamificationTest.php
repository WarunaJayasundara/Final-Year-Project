<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Game;
use App\Models\GameScore;
use App\Models\MissionClaim;
use App\Models\Question;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpLedgerEntry;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exercises the gamification module (XP/coins, badges, missions,
 * leaderboard) against the real dev database (no RefreshDatabase - see
 * AdaptivePlacementTest), with explicit tearDown cleanup. Relies on the
 * seeded Badge/Game catalogs (BadgeSeeder/GameSeeder) already being present.
 */
class GamificationTest extends TestCase
{
    private ?User $testUser = null;

    protected function tearDown(): void
    {
        if ($this->testUser) {
            $sessionIds = TestSession::where('user_id', $this->testUser->id)->pluck('id');
            \App\Models\SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();
            TestSession::whereIn('id', $sessionIds)->delete();
            GameScore::where('user_id', $this->testUser->id)->delete();
            UserBadge::where('user_id', $this->testUser->id)->delete();
            XpLedgerEntry::where('user_id', $this->testUser->id)->delete();
            MissionClaim::where('user_id', $this->testUser->id)->delete();
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    private function makeUser(string $tag): User
    {
        return User::create([
            'name' => 'Gamification Test User',
            'email' => "gamification-test-{$tag}-".uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);
    }

    public function test_game_score_submission_awards_xp_and_coins()
    {
        $this->testUser = $this->makeUser('game-xp');

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/games/memory_match/score', [
            'score' => 500,
            'duration_seconds' => 60,
        ]);

        $response->assertStatus(201);
        // memory_match scale is 1000 -> 500/1000*100 = 50% normalized -> xp=round(50*0.3)=15, coins=round(50*0.1)=5.
        $response->assertJsonPath('data.rewards.xp', 15);
        $response->assertJsonPath('data.rewards.coins', 5);
        $this->testUser->refresh();
        $this->assertGreaterThan(0, $this->testUser->xp);
        $this->assertGreaterThan(0, $this->testUser->coins);
        $this->assertEquals(1, XpLedgerEntry::where('user_id', $this->testUser->id)->count());
    }

    public function test_playing_every_seeded_game_awards_game_explorer_badge()
    {
        $this->testUser = $this->makeUser('game-explorer');

        foreach (Game::pluck('code') as $code) {
            $this->actingAs($this->testUser, 'web')->postJson("/api/games/{$code}/score", [
                'score' => 10,
                'duration_seconds' => 30,
            ])->assertStatus(201);
        }

        $earnedCodes = Badge::whereHas('userBadges', fn ($q) => $q->where('user_id', $this->testUser->id))->pluck('code');
        $this->assertContains('game_explorer', $earnedCodes);
    }

    public function test_high_score_awards_high_scorer_badge()
    {
        $this->testUser = $this->makeUser('high-scorer');

        // memory_match scale is 1000 in GamificationService - 900 normalizes to 90%, above the 85% threshold.
        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/games/memory_match/score', [
            'score' => 900,
            'duration_seconds' => 45,
        ]);

        $response->assertStatus(201);
        $newBadgeCodes = collect($response->json('data.rewards.new_badges'))->pluck('code');
        $this->assertContains('high_scorer', $newBadgeCodes);
    }

    public function test_missions_reflect_live_progress_and_can_be_claimed_once()
    {
        $this->testUser = $this->makeUser('missions');

        $missions = $this->actingAs($this->testUser, 'web')->getJson('/api/gamification/missions');
        $missions->assertStatus(200);
        $dailyGame = collect($missions->json('data'))->firstWhere('code', 'daily_game');
        $this->assertNotNull($dailyGame);
        $this->assertFalse($dailyGame['completed']);

        $this->actingAs($this->testUser, 'web')->postJson('/api/games/math_rush/score', [
            'score' => 5,
            'duration_seconds' => 20,
        ])->assertStatus(201);

        $missionsAfter = $this->actingAs($this->testUser, 'web')->getJson('/api/gamification/missions');
        $dailyGameAfter = collect($missionsAfter->json('data'))->firstWhere('code', 'daily_game');
        $this->assertTrue($dailyGameAfter['completed']);
        $this->assertFalse($dailyGameAfter['claimed']);

        $xpBefore = $this->testUser->fresh()->xp;

        $claim = $this->actingAs($this->testUser, 'web')->postJson('/api/gamification/missions/daily_game/claim');
        $claim->assertStatus(200);

        $this->testUser->refresh();
        $this->assertEquals($xpBefore + $dailyGameAfter['xp_reward'], $this->testUser->xp);
        $this->assertEquals(1, MissionClaim::where('user_id', $this->testUser->id)->where('mission_code', 'daily_game')->count());

        // Claiming again for the same period must be rejected, not double-awarded.
        $this->actingAs($this->testUser, 'web')->postJson('/api/gamification/missions/daily_game/claim')->assertStatus(422);
        $this->testUser->refresh();
        $this->assertEquals($xpBefore + $dailyGameAfter['xp_reward'], $this->testUser->xp);
    }

    public function test_leaderboard_ranks_users_by_xp_descending()
    {
        $this->testUser = $this->makeUser('leaderboard');
        $this->testUser->update(['xp' => 5000]);

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/gamification/leaderboard');

        $response->assertStatus(200);
        $top = collect($response->json('data.top'));
        $you = $top->firstWhere('user_id', $this->testUser->id);
        $this->assertNotNull($you, 'User with 5000 XP should appear in the top leaderboard entries.');
        $this->assertEquals(1, $you['rank']);
    }

    public function test_summary_endpoint_reports_level_and_badge_counts()
    {
        $this->testUser = $this->makeUser('summary');
        $this->testUser->update(['xp' => 150, 'coins' => 20]);

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/gamification/summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.level', 2);
        $response->assertJsonPath('data.coins', 20);
        $response->assertJsonPath('data.badges_total', Badge::count());
    }
}

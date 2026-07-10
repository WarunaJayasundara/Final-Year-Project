<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\User;
use App\Services\Analytics\StreakService;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\MissionService;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function __construct(
        private GamificationService $gamification,
        private MissionService $missions,
        private StreakService $streak,
    ) {
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $summary = $this->gamification->summary($user);
        $totalBadges = Badge::count();
        $earnedBadges = $user->badges()->count();

        return response()->json(['data' => array_merge($summary, [
            'streak_days' => $this->streak->calculate($user->id),
            'badges_earned' => $earnedBadges,
            'badges_total' => $totalBadges,
        ])]);
    }

    public function badges(Request $request)
    {
        $userId = $request->user()->id;

        $earnedAt = $request->user()->badges()->pluck('earned_at', 'badge_id');

        $badges = Badge::orderBy('id')->get()->map(fn (Badge $badge) => [
            'code' => $badge->code,
            'name_en' => $badge->name_en,
            'name_si' => $badge->name_si,
            'description_en' => $badge->description_en,
            'description_si' => $badge->description_si,
            'icon' => $badge->icon,
            'xp_reward' => $badge->xp_reward,
            'coin_reward' => $badge->coin_reward,
            'earned_at' => $earnedAt->get($badge->id),
        ]);

        return response()->json(['data' => $badges]);
    }

    public function missions(Request $request)
    {
        return response()->json(['data' => $this->missions->list($request->user())]);
    }

    public function claimMission(Request $request, string $code)
    {
        try {
            $mission = $this->missions->claim($request->user(), $code);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $mission,
            'summary' => $this->gamification->summary($request->user()->fresh()),
        ]);
    }

    /**
     * Cohort-wide XP ranking. Names are shown (not anonymized) - consistent
     * with the existing admin analytics, which already display full student
     * names to the small, trusted cohort this platform serves.
     */
    public function leaderboard(Request $request)
    {
        $top = User::where('role', 'user')
            ->orderByDesc('xp')
            ->limit(20)
            ->get(['id', 'name', 'xp', 'coins'])
            ->values()
            ->map(fn (User $user, int $index) => [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'name' => $user->name,
                'xp' => $user->xp,
                'level' => $this->gamification->levelForXp($user->xp),
                'is_you' => $user->id === $request->user()->id,
            ]);

        $yourRank = $top->firstWhere('is_you', true)
            ? null
            : User::where('role', 'user')->where('xp', '>', $request->user()->xp)->count() + 1;

        return response()->json(['data' => [
            'top' => $top,
            'your_rank' => $yourRank,
        ]]);
    }
}

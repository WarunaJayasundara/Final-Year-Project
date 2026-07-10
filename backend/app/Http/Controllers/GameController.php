<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameScore;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GameController extends Controller
{
    public function __construct(private GamificationService $gamification, private BadgeService $badges)
    {
    }

    public function index()
    {
        return response()->json(['data' => Game::orderBy('name_en')->get()]);
    }

    public function submitScore(Request $request, string $code)
    {
        $game = Game::where('code', $code)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'score' => ['required', 'integer'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $gameScore = GameScore::create([
            'user_id' => $request->user()->id,
            'game_id' => $game->id,
            'score' => $request->input('score'),
            'duration_seconds' => $request->input('duration_seconds'),
            'metadata' => $request->input('metadata'),
            'played_at' => now(),
        ]);

        $bestScore = GameScore::where('user_id', $request->user()->id)
            ->where('game_id', $game->id)
            ->max('score');

        $user = $request->user();
        [$xp, $coins] = $this->gamification->gameRewards($gameScore->fresh(['game']));
        $this->gamification->award($user, $xp, $coins, "game_score:{$code}");
        $newBadges = $this->badges->evaluate($user->fresh());

        return response()->json(['data' => [
            'game_score' => $gameScore,
            'best_score' => $bestScore,
            'is_new_best' => $gameScore->score >= $bestScore,
            'rewards' => [
                'xp' => $xp,
                'coins' => $coins,
                'new_badges' => array_map(fn ($b) => $b->toRewardArray(), $newBadges),
            ],
        ]], 201);
    }

    public function myScores(Request $request, string $code)
    {
        $game = Game::where('code', $code)->firstOrFail();

        $scores = GameScore::where('user_id', $request->user()->id)
            ->where('game_id', $game->id)
            ->orderByDesc('played_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $scores]);
    }
}

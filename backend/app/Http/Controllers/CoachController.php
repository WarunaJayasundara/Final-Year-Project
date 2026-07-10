<?php

namespace App\Http\Controllers;

use App\Contracts\AiCoachServiceInterface;
use App\Models\AiCoachLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CoachController extends Controller
{
    public function __construct(private AiCoachServiceInterface $coach)
    {
    }

    public function chat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        AiCoachLog::create(['user_id' => $user->id, 'asked_at' => now()]);

        $reply = $this->coach->chat(
            $user,
            $request->input('message'),
            $request->input('history', []),
            $user->locale
        );

        return response()->json(['data' => ['reply' => $reply]]);
    }
}

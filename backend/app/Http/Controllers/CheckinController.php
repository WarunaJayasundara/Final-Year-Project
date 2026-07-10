<?php

namespace App\Http\Controllers;

use App\Models\UserDailyCheckin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Captures the three exam-readiness inputs that have no other source on the
 * platform (study hours, motivation, attendance) - see the
 * user_daily_checkins migration comment for why these are self-reported.
 */
class CheckinController extends Controller
{
    public function today(Request $request)
    {
        $checkin = UserDailyCheckin::where('user_id', $request->user()->id)
            ->where('checkin_date', now()->toDateString())
            ->first();

        return response()->json(['data' => $checkin]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'study_hours' => ['required', 'numeric', 'min:0', 'max:16'],
            'motivation_score' => ['required', 'integer', 'min:1', 'max:10'],
            'attended' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $checkin = UserDailyCheckin::updateOrCreate(
            ['user_id' => $request->user()->id, 'checkin_date' => now()->toDateString()],
            $validator->validated()
        );

        return response()->json(['data' => $checkin]);
    }
}

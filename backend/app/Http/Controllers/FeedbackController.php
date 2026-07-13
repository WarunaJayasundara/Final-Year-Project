<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'overall_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'ui_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'question_quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'sinhala_quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'usefulness_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'suggestion' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $feedback = Feedback::create(array_merge($validator->validated(), [
            'user_id' => $request->user()->id,
            'locale' => $request->user()->locale,
        ]));

        return response()->json(['data' => $this->present($feedback)], 201);
    }

    public function mine(Request $request)
    {
        $feedback = Feedback::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Feedback $f) => $this->present($f));

        return response()->json(['data' => $feedback]);
    }

    private function present(Feedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'overall_rating' => $feedback->overall_rating,
            'ui_rating' => $feedback->ui_rating,
            'question_quality_rating' => $feedback->question_quality_rating,
            'sinhala_quality_rating' => $feedback->sinhala_quality_rating,
            'usefulness_rating' => $feedback->usefulness_rating,
            'comment' => $feedback->comment,
            'suggestion' => $feedback->suggestion,
            'created_at' => $feedback->created_at->toIso8601String(),
        ];
    }
}

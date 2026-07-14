<?php

namespace App\Http\Controllers\Sessions;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\UserProgressSnapshot;
use App\Services\Sessions\QuestionSamplingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Personalized mock-exam generator: student-configured question
 * count/duration/scope/difficulty, with weak categories over-represented
 * (QuestionSamplingService::sampleForMockExam()). A mock exam is a
 * TestSession like any other (session_type='mock', a real
 * time_limit_seconds) - everything after creation reuses
 * TestSessionController's generic answers/complete/report endpoints rather
 * than a parallel data model.
 */
class MockExamController extends Controller
{
    public function __construct(private QuestionSamplingService $sampler)
    {
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->current_level_id) {
            return response()->json(['message' => 'Complete the placement test before taking a mock exam.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'total_questions' => ['nullable', 'integer', 'min:10', 'max:150'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'scope' => ['nullable', 'in:full_syllabus,selected_categories'],
            'category_ids' => ['required_if:scope,selected_categories', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'difficulty_mode' => ['nullable', 'in:standard,adaptive'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $totalQuestions = (int) $request->input('total_questions', 25);
        $durationMinutes = (int) $request->input('duration_minutes', 30);
        $scope = $request->input('scope', 'full_syllabus');
        $adaptive = $request->input('difficulty_mode', 'standard') === 'adaptive';

        $categoryIds = $scope === 'selected_categories'
            ? collect($request->input('category_ids'))->map(fn ($id) => (int) $id)
            : Category::pluck('id');

        if ($categoryIds->isEmpty()) {
            return response()->json(['message' => 'No categories available for a mock exam.'], 422);
        }

        $categoryAccuracy = $this->categoryAccuracyMap($user->id, $categoryIds);

        $questions = $this->sampler->sampleForMockExam(
            $user->id,
            $user->currentLevel,
            $totalQuestions,
            $categoryIds,
            $categoryAccuracy,
            $adaptive
        );

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'Not enough questions available yet for a mock exam with these settings.'], 422);
        }

        $session = DB::transaction(function () use ($user, $questions, $durationMinutes) {
            $session = TestSession::create([
                'user_id' => $user->id,
                'session_type' => 'mock',
                'category_id' => null,
                'level_id' => $user->current_level_id,
                'total_questions' => $questions->count(),
                'time_limit_seconds' => $durationMinutes * 60,
                'started_at' => now(),
            ]);

            $rows = $questions->map(fn ($question) => [
                'test_session_id' => $session->id,
                'question_id' => $question->id,
                'is_correct' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            SessionAnswer::insert($rows);

            return $session;
        });

        $questionPayload = $session->answers()
            ->with('question')
            ->orderBy('id')
            ->get()
            ->map(fn (SessionAnswer $answer) => array_merge(
                $answer->question->toClientArray($user->locale),
                ['answer_id' => $answer->id, 'answered' => false]
            ));

        return response()->json(['data' => [
            'id' => $session->id,
            'session_type' => $session->session_type,
            'is_adaptive' => false,
            'category_id' => null,
            'level_id' => $session->level_id,
            'total_questions' => $session->total_questions,
            'time_limit_seconds' => $session->time_limit_seconds,
            'completed_at' => null,
            'questions' => $questionPayload,
        ]], 201);
    }

    /** @return array<int,float> category_id => accuracy_percent */
    private function categoryAccuracyMap(int $userId, $categoryIds): array
    {
        $latestByCategory = UserProgressSnapshot::where('user_id', $userId)
            ->whereIn('category_id', $categoryIds)
            ->orderByDesc('snapshot_date')
            ->get()
            ->groupBy('category_id');

        $map = [];
        foreach ($categoryIds as $categoryId) {
            $latest = $latestByCategory->get($categoryId)?->first()?->accuracy_percent;
            $map[$categoryId] = $latest !== null ? (float) $latest : 50.0;
        }

        return $map;
    }
}

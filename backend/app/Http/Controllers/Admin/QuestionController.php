<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = Question::with(['category', 'level']);

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($levelId = $request->query('level_id')) {
            $query->where('level_id', $levelId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $questions = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($questions);
    }

    public function store(StoreQuestionRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['difficulty_weight'] = $data['difficulty_weight'] ?? 1;

        $question = Question::create($data);

        return response()->json(['data' => $question->fresh(['category', 'level'])], 201);
    }

    public function show(Question $question)
    {
        return response()->json(['data' => $question->load(['category', 'level'])]);
    }

    public function update(UpdateQuestionRequest $request, Question $question)
    {
        $data = $request->validated();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $question->update($data);

        return response()->json(['data' => $question->fresh(['category', 'level'])]);
    }

    public function destroy(Question $question)
    {
        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    /**
     * Upload/replace the image for an image-type (spatial pattern) question.
     */
    public function uploadImage(Request $request, Question $question)
    {
        $validator = Validator::make($request->all(), [
            'image' => ['required', 'image', 'max:4096'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $path = $request->file('image')->store('questions/spatial', 'public');
        $question->update(['image_path' => $path]);

        return response()->json(['data' => $question->fresh()]);
    }
}

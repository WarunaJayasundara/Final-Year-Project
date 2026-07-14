<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Question;
use App\Services\QuestionBank\VisualQuestionGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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

    /** Upload/replace the image for an image-type (spatial pattern) question. */
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

    /**
     * Generates a preview-only visual question via VisualQuestionGeneratorService
     * and writes its SVG to storage. To actually save it, POST the same
     * payload (incl. this response's image_path) to store() - there's no
     * separate creation path. Only 'shape_rotation' is wired up; other
     * SvgFigureBuilder archetypes remain seeder-only (documented scope cut).
     */
    public function generateVisualPreview(Request $request, VisualQuestionGeneratorService $generator)
    {
        $validated = $request->validate([
            'pattern_type' => ['required', 'in:shape_rotation'],
            'level_id' => ['required', 'exists:iq_levels,id'],
        ]);

        $level = \App\Models\IqLevel::find($validated['level_id'])->level_number ?? 1;

        $result = $generator->generateShapeRotation($level);

        $svg = $result['image_svg'];
        unset($result['image_svg']);

        $path = 'questions/generated/preview/'.Str::random(24).'.svg';
        Storage::disk('public')->put($path, $svg);
        $result['image_path'] = $path;

        return response()->json(['data' => $result]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneratedQuestion;
use App\Models\Category;
use App\Models\ExamProfile;
use App\Models\IqLevel;
use App\Models\SourceDocument;
use App\Services\AiQuestionGeneration\QuestionDraftService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiQuestionController extends Controller
{
    public function __construct(private QuestionDraftService $drafts)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $drafts = AiGeneratedQuestion::with(['category', 'level'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($drafts);
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => ['required', 'exists:categories,id'],
            'level_id' => ['required', 'exists:iq_levels,id'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'exam_category' => ['nullable', 'string', 'in:'.implode(',', array_keys(ExamProfile::EXAM_CATEGORIES))],
            'source_document_id' => ['nullable', 'exists:source_documents,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $category = Category::findOrFail($request->input('category_id'));
        $level = IqLevel::findOrFail($request->input('level_id'));
        $examCategoryLabel = $request->filled('exam_category')
            ? ExamProfile::EXAM_CATEGORIES[$request->input('exam_category')]
            : null;
        $sourceDocument = $request->filled('source_document_id')
            ? SourceDocument::findOrFail($request->input('source_document_id'))
            : null;

        $drafts = $this->drafts->generateDrafts(
            $category,
            $level,
            (int) $request->input('count', 3),
            $examCategoryLabel,
            $request->user()->id,
            $sourceDocument,
        );

        return response()->json(['data' => Collection::make($drafts)->load(['category', 'level'])], 201);
    }

    public function approve(Request $request, AiGeneratedQuestion $aiQuestion)
    {
        if ($aiQuestion->status !== 'pending') {
            return response()->json(['message' => 'This draft has already been reviewed.'], 422);
        }

        $question = $this->drafts->approve($aiQuestion, $request->user());

        return response()->json(['data' => [
            'draft' => $aiQuestion->fresh(),
            'question' => $question->fresh(['category', 'level']),
        ]]);
    }

    public function reject(Request $request, AiGeneratedQuestion $aiQuestion)
    {
        if ($aiQuestion->status !== 'pending') {
            return response()->json(['message' => 'This draft has already been reviewed.'], 422);
        }

        $this->drafts->reject($aiQuestion, $request->user());

        return response()->json(['data' => $aiQuestion->fresh()]);
    }

    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:ai_generated_questions,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->drafts->bulkApprove($request->input('ids'), $request->user());

        return response()->json([
            'data' => [
                'approved_count' => count($result['approved']),
                'approved_question_ids' => Collection::make($result['approved'])->pluck('id'),
                'skipped_ids' => $result['skipped'],
            ],
        ]);
    }
}

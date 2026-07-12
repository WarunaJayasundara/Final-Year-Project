<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SourceDocument;
use App\Models\StudyNote;
use App\Services\StudyNotes\StudyNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudyNoteController extends Controller
{
    public function __construct(private StudyNoteService $notes)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'draft');

        $notes = StudyNote::with(['category', 'sourceDocument'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notes);
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source_document_id' => ['required', 'exists:source_documents,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $document = SourceDocument::findOrFail($request->input('source_document_id'));

        if ($document->document_type !== 'theory_book') {
            return response()->json(['message' => 'Study notes can only be generated from theory_book documents - this document is type "'.$document->document_type.'".'], 422);
        }

        if ($document->analysis_status !== 'analyzed') {
            return response()->json(['message' => 'Analyze this document first before generating study notes from it.'], 422);
        }

        $category = $request->filled('category_id') ? Category::findOrFail($request->input('category_id')) : null;

        $note = $this->notes->generateFromDocument($document, $category, $request->user()->id);

        return response()->json(['data' => $note->load(['category', 'sourceDocument'])], 201);
    }

    public function publish(Request $request, StudyNote $studyNote)
    {
        if ($studyNote->status !== 'draft') {
            return response()->json(['message' => 'This note has already been reviewed.'], 422);
        }

        $note = $this->notes->publish($studyNote, $request->user());

        return response()->json(['data' => $note->fresh()]);
    }

    public function reject(Request $request, StudyNote $studyNote)
    {
        if ($studyNote->status !== 'draft') {
            return response()->json(['message' => 'This note has already been reviewed.'], 422);
        }

        $note = $this->notes->reject($studyNote, $request->user());

        return response()->json(['data' => $note->fresh()]);
    }
}

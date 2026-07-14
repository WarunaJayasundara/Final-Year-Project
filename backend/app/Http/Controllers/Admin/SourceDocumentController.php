<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceDocument;
use App\Services\QuestionBank\PdfIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Admin "Knowledge & Question Source Library": upload a reference PDF,
 * extract its text and suggest topics (see PdfIngestionService), then hand
 * off to QuestionDraftService::generateDrafts() (via
 * AiQuestionController::generate, passing source_document_id) for drafts
 * grounded in the selected document.
 *
 * Files live on the private `local` disk, never publicly served - several
 * uploaded reference works are copyrighted commercial books/past papers.
 */
class SourceDocumentController extends Controller
{
    public function __construct(private PdfIngestionService $ingestion)
    {
    }

    public function index()
    {
        return response()->json(
            SourceDocument::with('uploader')->orderByDesc('created_at')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:past_paper,iq_book,exam_guide,theory_book,other'],
            'exam_type_tags' => ['nullable', 'array'],
            'exam_type_tags.*' => ['string'],
            'year' => ['nullable', 'string', 'max:10'],
            'reliability_note' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $path = $request->file('file')->store('source_documents', 'local');

        $document = SourceDocument::create([
            'title' => $request->input('title'),
            'document_type' => $request->input('document_type'),
            'exam_type_tags' => $request->input('exam_type_tags', []),
            'year' => $request->input('year'),
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'analysis_status' => 'pending',
            'reliability_note' => $request->input('reliability_note'),
        ]);

        return response()->json(['data' => $document], 201);
    }

    public function show(SourceDocument $sourceDocument)
    {
        return response()->json(['data' => $sourceDocument->load('uploader')]);
    }

    public function analyze(SourceDocument $sourceDocument)
    {
        $sourceDocument->update(['analysis_status' => 'analyzing']);

        try {
            $absolutePath = Storage::disk('local')->path($sourceDocument->file_path);
            $text = $this->ingestion->extractText($absolutePath);
            $patterns = $this->ingestion->detectPatterns($text);

            $note = $sourceDocument->reliability_note;
            if ($patterns['unicode_sinhala_char_count'] < 5 && $patterns['approx_word_count'] > 50) {
                $note = trim(($note ?? '')."\nLow/zero real Sinhala Unicode character count detected in extracted text - if this document is Sinhala-language, it likely uses a legacy non-Unicode font and the extracted text is unreliable (mojibake). Manual review required before using it as a generation source.");
            }

            $sourceDocument->update([
                'extracted_topics' => $this->ingestion->suggestTopics($text),
                'detected_patterns' => $patterns,
                'extracted_theory_concepts' => $this->ingestion->buildKnowledgeMap($text),
                'analysis_status' => 'analyzed',
                'reliability_note' => $note,
            ]);
        } catch (\Throwable $e) {
            $sourceDocument->update([
                'analysis_status' => 'failed',
                'reliability_note' => trim(($sourceDocument->reliability_note ?? '')."\nAnalysis failed: {$e->getMessage()}"),
            ]);

            return response()->json(['message' => 'Analysis failed.', 'error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $sourceDocument->fresh()]);
    }

    public function destroy(SourceDocument $sourceDocument)
    {
        Storage::disk('local')->delete($sourceDocument->file_path);
        $sourceDocument->delete();

        return response()->json(['message' => 'Source document deleted.']);
    }
}

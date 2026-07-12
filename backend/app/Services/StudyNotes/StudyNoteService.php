<?php

namespace App\Services\StudyNotes;

use App\Contracts\StudyNoteGeneratorServiceInterface;
use App\Models\Category;
use App\Models\SourceDocument;
use App\Models\StudyNote;
use App\Models\User;
use App\Services\QuestionBank\PdfIngestionService;
use Illuminate\Support\Facades\Storage;

/**
 * Orchestrates study-note generation from an analyzed theory-book source
 * document: re-extracts its text (not stored in full on the model, to keep
 * the DB lean and avoid persisting large copyrighted excerpts - see
 * SourceDocumentController's own docblock), calls the bound generator
 * (Gemini or Mock), and persists the result as a *draft* note - never
 * directly visible to students. Only publish() (an explicit admin action)
 * makes a note visible, same human-in-the-loop gate as
 * QuestionDraftService::approve() and for the same reason: a confidently
 * wrong "teaching" explanation is worse than none at all.
 */
class StudyNoteService
{
    public function __construct(
        private StudyNoteGeneratorServiceInterface $generator,
        private PdfIngestionService $ingestion,
    ) {
    }

    public function generateFromDocument(SourceDocument $document, ?Category $category, ?int $generatedBy): StudyNote
    {
        $text = $this->ingestion->extractText(Storage::disk('local')->path($document->file_path));
        $matchedTopics = collect($document->extracted_topics ?? $this->ingestion->suggestTopics($text))
            ->pluck('topic')
            ->take(5)
            ->all();

        $draft = $this->generator->generate($document->title, $text, $matchedTopics);

        return StudyNote::create([
            'source_document_id' => $document->id,
            'category_id' => $category?->id,
            'subcategory' => $matchedTopics[0] ?? null,
            'title_en' => $draft['title_en'],
            'title_si' => $draft['title_si'],
            'learning_objective_en' => $draft['learning_objective_en'] ?? null,
            'learning_objective_si' => $draft['learning_objective_si'] ?? null,
            'content_en' => $draft['content_en'],
            'content_si' => $draft['content_si'],
            'worked_example_en' => $draft['worked_example_en'] ?? null,
            'worked_example_si' => $draft['worked_example_si'] ?? null,
            'key_technique_en' => $draft['key_technique_en'] ?? null,
            'key_technique_si' => $draft['key_technique_si'] ?? null,
            'common_mistakes_en' => $draft['common_mistakes_en'] ?? null,
            'common_mistakes_si' => $draft['common_mistakes_si'] ?? null,
            'key_concepts' => $draft['key_concepts'] ?? $matchedTopics,
            'generation_method' => $this->generator instanceof GeminiStudyNoteGeneratorService && config('services.gemini.api_key') ? 'gemini' : 'mock',
            'status' => 'draft',
            'generated_by' => $generatedBy,
        ]);
    }

    public function publish(StudyNote $note, User $reviewer): StudyNote
    {
        $note->update([
            'status' => 'published',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $note;
    }

    public function reject(StudyNote $note, User $reviewer): StudyNote
    {
        $note->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return $note;
    }
}

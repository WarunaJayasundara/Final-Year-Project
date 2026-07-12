<?php

namespace App\Contracts;

interface StudyNoteGeneratorServiceInterface
{
    /**
     * Generate one teaching/study note from a bounded excerpt of a theory
     * document's extracted text. Returns a plain array matching the shape
     * expected by StudyNoteService (not yet persisted) - the caller handles
     * persistence and the draft->review->publish workflow.
     *
     * @param string $documentTitle The source document's title, for context.
     * @param string $textExcerpt A bounded excerpt of extracted text (never
     *   the full document) - implementations must not reproduce this
     *   verbatim in the output, only use it as grounding for an original
     *   summary, since source material may be copyrighted.
     * @param string[] $matchedTopics Keyword-matched topic labels from
     *   PdfIngestionService::suggestTopics(), for category/subcategory hints.
     */
    public function generate(string $documentTitle, string $textExcerpt, array $matchedTopics): array;
}

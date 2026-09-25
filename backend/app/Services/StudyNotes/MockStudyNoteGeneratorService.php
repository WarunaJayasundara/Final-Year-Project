<?php

namespace App\Services\StudyNotes;

use App\Contracts\StudyNoteGeneratorServiceInterface;
use App\Models\Question;

/** Honest fallback note generator - it cannot genuinely summarize or teach from text it has no real comprehension of. */
class MockStudyNoteGeneratorService implements StudyNoteGeneratorServiceInterface
{
    public function generate(string $documentTitle, string $textExcerpt, array $matchedTopics): array
    {
        $topicList = $matchedTopics !== [] ? implode(', ', $matchedTopics) : 'general aptitude';
        $example = $this->findWorkedExample($matchedTopics);

        return [
            'title_en' => "Topic Index: {$documentTitle}",
            'title_si' => "තේමා: {$documentTitle}",
            'learning_objective_en' => "Recognise and work through {$topicList} problems.",
            'learning_objective_si' => "{$topicList} ගැටලු හඳුනාගෙන විසඳීම.",
            'content_en' => "This document's keyword analysis matched the following topics: {$topicList}. "
                .'This is an automatically detected topic index, not an AI-written explanation - '
                .'a real teaching summary requires the Gemini-backed generator to be configured.',
            'content_si' => "ලේඛනය: {$documentTitle}. තේමා: {$topicList}.",
            'worked_example_en' => $example?->question_text_en,
            'worked_example_si' => $example?->question_text_si,
            'key_technique_en' => $example ? "See this bank question's own explanation: {$example->explanation_en}" : null,
            'key_technique_si' => $example?->explanation_si,
            'common_mistakes_en' => null,
            'common_mistakes_si' => null,
            'key_concepts' => $matchedTopics,
        ];
    }

    /** Pulls one real, already-verified question for the matched topic/subcategory as an honest worked example - never invents one. */
    private function findWorkedExample(array $matchedTopics): ?Question
    {
        foreach ($matchedTopics as $topic) {
            $question = Question::where('is_active', true)
                ->where('subcategory', $topic)
                ->whereNotNull('explanation_en')
                ->inRandomOrder()
                ->first();
            if ($question) {
                return $question;
            }
        }

        return null;
    }
}

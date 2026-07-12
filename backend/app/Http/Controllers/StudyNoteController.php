<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\StudyNote;
use App\Services\Analytics\SpacedRepetitionService;
use App\Services\Analytics\StudyNoteRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Student-facing "self-learning" reading list: only ever returns published
 * notes (never drafts/rejected) - the admin review gate in
 * Admin\StudyNoteController is what makes a note eligible to appear here.
 * Also exposes the spaced-repetition queue, retrieval-practice questions,
 * and the weak-area-triggered lesson recommendation (brief §9/§10).
 */
class StudyNoteController extends Controller
{
    public function __construct(
        private SpacedRepetitionService $spacedRepetition,
        private StudyNoteRecommendationService $recommendation,
    ) {
    }

    public function index(Request $request)
    {
        $notes = StudyNote::with('category')
            ->where('status', 'published')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->query('category_id')))
            ->when($request->filled('subcategory'), fn ($q) => $q->where('subcategory', $request->query('subcategory')))
            ->orderByDesc('reviewed_at')
            ->paginate(20);

        return response()->json($notes);
    }

    public function show(StudyNote $studyNote)
    {
        abort_unless($studyNote->status === 'published', 404);

        return response()->json(['data' => $studyNote->load('category')]);
    }

    /** Notes due for spaced-repetition review today, for this student. */
    public function dueToday(Request $request)
    {
        $due = $this->spacedRepetition->dueToday($request->user());

        return response()->json(['data' => $due]);
    }

    /** Records a self-graded recall result (again/hard/good/easy) and advances the review schedule. */
    public function review(Request $request, StudyNote $studyNote)
    {
        abort_unless($studyNote->status === 'published', 404);

        $validated = $request->validate([
            'result' => ['required', Rule::in(['again', 'hard', 'good', 'easy'])],
        ]);

        $review = $this->spacedRepetition->recordResult($request->user(), $studyNote, $validated['result']);

        return response()->json(['data' => $review]);
    }

    /**
     * 2-3 real practice questions from the note's linked subcategory -
     * "test yourself" retrieval practice. Unlike toClientArray() (used by
     * real assessment sessions, which must never leak the answer before
     * submission), this DOES include the correct option and explanation
     * directly: it's an unscored self-check tool with no theta/session
     * behind it, not part of the proctored assessment instrument.
     */
    public function practiceQuestions(Request $request, StudyNote $studyNote)
    {
        abort_unless($studyNote->status === 'published', 404);

        $locale = $request->user()->locale ?? 'en';
        $questions = Question::where('is_active', true)
            ->where('subcategory', $studyNote->subcategory)
            ->inRandomOrder()
            ->limit(3)
            ->get()
            ->map(fn (Question $q) => [
                'id' => $q->id,
                'question_text' => $locale === 'si' ? $q->question_text_si : $q->question_text_en,
                'image_path' => $q->image_path,
                'options' => collect($q->options)->map(fn (array $o) => [
                    'key' => $o['key'],
                    'text' => $o["text_{$locale}"] ?? $o['text_en'] ?? null,
                ])->values()->all(),
                'correct_option_key' => $q->correct_option_key,
                'explanation' => $locale === 'si' ? $q->explanation_si : $q->explanation_en,
            ]);

        return response()->json(['data' => $questions]);
    }

    /** The single weakest-subcategory note recommendation for this student, if any. */
    public function recommendation(Request $request)
    {
        $result = $this->recommendation->recommendFor($request->user());
        if (! $result) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'subcategory' => $result['subcategory'],
                'accuracy' => $result['accuracy'],
                'study_note' => $result['study_note']->load('category'),
            ],
        ]);
    }
}

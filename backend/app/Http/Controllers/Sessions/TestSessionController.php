<?php

namespace App\Http\Controllers\Sessions;

use App\Contracts\AiFeedbackServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Services\Analytics\ProgressSnapshotService;
use App\Services\Gamification\BadgeService;
use App\Services\Gamification\GamificationService;
use App\Services\Irt\AbilityEstimationService;
use App\Services\Irt\AdaptiveItemSelectionService;
use App\Services\Leveling\LevelAdjustmentService;
use App\Services\Sessions\QuestionSamplingService;
use App\Services\Sessions\WeakAreaWeightingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TestSessionController extends Controller
{
    /**
     * The placement test is the platform's true computerized adaptive test
     * (CAT): items are delivered one at a time, each chosen to maximize
     * information at the student's current ability estimate, re-estimated via
     * MLE after every answer. It stops once either the max item count is
     * reached, or (after a minimum number of items, to avoid stopping on a
     * lucky/unlucky early streak) the ability estimate's standard error drops
     * below the stopping threshold - both are standard CAT termination rules.
     */
    private const PLACEMENT_MIN_ITEMS = 15;

    private const PLACEMENT_MAX_ITEMS = 25;

    private const PLACEMENT_SE_STOP_THRESHOLD = 0.35;

    public function __construct(
        private QuestionSamplingService $sampler,
        private LevelAdjustmentService $leveling,
        private ProgressSnapshotService $snapshots,
        private AiFeedbackServiceInterface $aiFeedback,
        private AbilityEstimationService $abilityEstimation,
        private AdaptiveItemSelectionService $itemSelector,
        private GamificationService $gamification,
        private BadgeService $badges,
        private WeakAreaWeightingService $weakAreaWeighting,
    ) {
    }

    public function startPlacement(Request $request)
    {
        $user = $request->user();

        if ($user->placement_completed_at) {
            return response()->json(['message' => 'Placement test already completed.'], 422);
        }

        $rotation = $this->itemSelector->categoryRotation();

        if (empty($rotation) || Question::where('is_active', true)->count() < self::PLACEMENT_MIN_ITEMS) {
            return response()->json(['message' => 'Not enough questions seeded yet to run a placement test.'], 422);
        }

        $nominalLevel = IqLevel::where('level_number', 3)->firstOrFail();

        [$session, $firstAnswer] = DB::transaction(function () use ($user, $nominalLevel, $rotation) {
            $session = TestSession::create([
                'user_id' => $user->id,
                'session_type' => 'placement',
                'category_id' => null,
                'level_id' => $nominalLevel->id,
                'total_questions' => self::PLACEMENT_MAX_ITEMS,
                'started_at' => now(),
                'theta' => 0.0,
            ]);

            $excludeIds = $this->seenQuestionIds($user->id);
            $firstItem = $this->itemSelector->selectNext($rotation[0], 0.0, $excludeIds)
                ?? $this->itemSelector->selectNextAnyCategory(0.0, $excludeIds);

            if (! $firstItem) {
                abort(422, 'Not enough questions seeded yet to run a placement test.');
            }

            $firstAnswer = SessionAnswer::create([
                'test_session_id' => $session->id,
                'question_id' => $firstItem->id,
                'is_correct' => false,
            ]);

            return [$session, $firstAnswer];
        });

        return response()->json($this->adaptivePlacementPayload($session, $firstAnswer, 0, $user->locale), 201);
    }

    public function startDaily(Request $request)
    {
        $user = $request->user();

        if (! $user->current_level_id) {
            return response()->json(['message' => 'Complete the placement test before starting a daily session.'], 422);
        }

        $totalQuestions = (int) $request->input('total_questions', 30);
        $totalQuestions = max(25, min(50, $totalQuestions));

        $allocation = $this->weakAreaWeighting->allocationFor($user->id, $totalQuestions);
        $questions = $this->sampler->sampleForDaily($user->id, $user->currentLevel, $totalQuestions, $allocation);

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No questions available at your level yet.'], 422);
        }

        $session = $this->createBatchSession($user->id, 'daily', null, $user->current_level_id, $questions);

        return response()->json($this->sessionPayload($session, $user->locale), 201);
    }

    public function startPractice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => ['required', 'exists:categories,id'],
            'total_questions' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (! $user->current_level_id) {
            return response()->json(['message' => 'Complete the placement test before practicing.'], 422);
        }

        $category = Category::findOrFail($request->input('category_id'));
        $totalQuestions = (int) $request->input('total_questions', 20);

        $questions = $this->sampler->sampleForPractice($user->id, $category, $user->currentLevel, $totalQuestions);

        if ($questions->isEmpty()) {
            return response()->json(['message' => 'No questions available for this category/level yet.'], 422);
        }

        $session = $this->createBatchSession($user->id, 'practice', $category->id, $user->current_level_id, $questions);

        return response()->json($this->sessionPayload($session, $user->locale), 201);
    }

    public function show(Request $request, TestSession $session)
    {
        $this->authorizeOwner($request, $session);

        if ($session->session_type === 'placement' && ! $session->completed_at) {
            $current = $session->answers()->whereNull('answered_at')->with('question')->first();
            $answeredCount = $session->answers()->whereNotNull('answered_at')->count();

            if ($current) {
                return response()->json($this->adaptivePlacementPayload($session, $current, $answeredCount, $request->user()->locale));
            }
        }

        return response()->json($this->sessionPayload($session, $request->user()->locale));
    }

    public function submitAnswer(Request $request, TestSession $session)
    {
        $this->authorizeOwner($request, $session);

        $validator = Validator::make($request->all(), [
            'question_id' => ['required', 'exists:questions,id'],
            'selected_option_key' => ['required', 'string', 'max:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $answer = SessionAnswer::where('test_session_id', $session->id)
            ->where('question_id', $request->input('question_id'))
            ->firstOrFail();

        $question = $answer->question;
        $selected = $request->input('selected_option_key');

        $answer->update([
            'selected_option_key' => $selected,
            'is_correct' => $selected === $question->correct_option_key,
            'answered_at' => now(),
        ]);

        if ($session->session_type === 'placement' && ! $session->completed_at) {
            return $this->handleAdaptiveAnswer($request, $session, $answer, $question);
        }

        return response()->json(['data' => [
            'question_id' => $question->id,
            'is_correct' => $answer->is_correct,
            'correct_option_key' => $question->correct_option_key,
        ]]);
    }

    public function complete(Request $request, TestSession $session)
    {
        $this->authorizeOwner($request, $session);

        if ($session->completed_at) {
            return response()->json($this->reportPayload($session, $request->user()->locale));
        }

        $answers = $session->answers()->get();
        $answeredCount = $answers->whereNotNull('answered_at')->count();
        $correctCount = $answers->where('is_correct', true)->count();
        $totalQuestions = $session->session_type === 'placement' ? $answeredCount : $session->total_questions;
        $scorePercent = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;

        $session->update([
            'total_questions' => $totalQuestions,
            'correct_count' => $correctCount,
            'score_percent' => $scorePercent,
            'completed_at' => now(),
        ]);

        $this->leveling->adjustLevelAfterSession($session->fresh());
        $this->snapshots->upsertForSession($session->fresh());

        $user = $request->user();
        [$xp, $coins] = $this->gamification->sessionRewards($session->fresh());
        $this->gamification->award($user, $xp, $coins, "session_complete:{$session->session_type}");
        $newBadges = $this->badges->evaluate($user->fresh());

        $payload = $this->reportPayload($session->fresh(), $user->locale);
        $payload['data']['rewards'] = [
            'xp' => $xp,
            'coins' => $coins,
            'new_badges' => array_map(fn ($b) => $b->toRewardArray(), $newBadges),
        ];

        return response()->json($payload);
    }

    public function report(Request $request, TestSession $session)
    {
        $this->authorizeOwner($request, $session);

        if (! $session->completed_at) {
            return response()->json(['message' => 'Session is not completed yet.'], 422);
        }

        return response()->json($this->reportPayload($session, $request->user()->locale));
    }

    public function explainAnswer(Request $request, TestSession $session, SessionAnswer $answer)
    {
        $this->authorizeOwner($request, $session);

        if ($answer->test_session_id !== $session->id) {
            abort(404);
        }

        if (! $answer->answered_at) {
            return response()->json(['message' => 'This question has not been answered yet.'], 422);
        }

        if (! $answer->ai_feedback_text) {
            $locale = $request->user()->locale;
            $feedback = $this->aiFeedback->explainAnswer($answer->question, $answer->selected_option_key, $locale);

            $answer->update([
                'ai_feedback_text' => $feedback,
                'ai_feedback_generated_at' => now(),
            ]);
        }

        return response()->json(['data' => ['ai_feedback_text' => $answer->fresh()->ai_feedback_text]]);
    }

    /**
     * After each placement answer: re-estimate theta from the session's
     * answers so far, decide via the CAT stopping rule whether to serve
     * another item or signal the frontend to call /complete, and if
     * continuing, adaptively select + persist the next item.
     */
    private function handleAdaptiveAnswer(Request $request, TestSession $session, SessionAnswer $answer, Question $question)
    {
        $answeredCount = $session->answers()->whereNotNull('answered_at')->count();

        $estimate = $this->abilityEstimation->estimateFromSession($session->id);
        $session->update(['theta' => $estimate['theta'], 'theta_se' => $estimate['se']]);

        $stopByCount = $answeredCount >= self::PLACEMENT_MAX_ITEMS;
        $stopByPrecision = $answeredCount >= self::PLACEMENT_MIN_ITEMS && $estimate['se'] <= self::PLACEMENT_SE_STOP_THRESHOLD;

        if (! $stopByCount && ! $stopByPrecision) {
            $excludeIds = $this->seenQuestionIds($request->user()->id)
                ->merge($session->answers()->pluck('question_id'));

            $rotation = $this->itemSelector->categoryRotation();
            $nextCategoryId = $rotation[$answeredCount % count($rotation)];

            $nextItem = $this->itemSelector->selectNext($nextCategoryId, $estimate['theta'], $excludeIds)
                ?? $this->itemSelector->selectNextAnyCategory($estimate['theta'], $excludeIds);

            if ($nextItem) {
                $nextAnswer = SessionAnswer::create([
                    'test_session_id' => $session->id,
                    'question_id' => $nextItem->id,
                    'is_correct' => false,
                ]);

                return response()->json(['data' => [
                    'question_id' => $question->id,
                    'is_correct' => $answer->is_correct,
                    'correct_option_key' => $question->correct_option_key,
                    'items_answered' => $answeredCount,
                    'theta' => $estimate['theta'],
                    'theta_se' => $estimate['se'],
                    'ready_to_complete' => false,
                    'next_question' => array_merge(
                        $nextItem->toClientArray($request->user()->locale),
                        ['answer_id' => $nextAnswer->id, 'answered' => false]
                    ),
                ]]);
            }
            // Fell through: no fresh item available anywhere - stop early below.
        }

        return response()->json(['data' => [
            'question_id' => $question->id,
            'is_correct' => $answer->is_correct,
            'correct_option_key' => $question->correct_option_key,
            'items_answered' => $answeredCount,
            'theta' => $estimate['theta'],
            'theta_se' => $estimate['se'],
            'ready_to_complete' => true,
            'next_question' => null,
        ]]);
    }

    private function authorizeOwner(Request $request, TestSession $session): void
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function seenQuestionIds(int $userId): Collection
    {
        return SessionAnswer::whereHas('session', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->pluck('question_id')->unique()->values();
    }

    private function createBatchSession(int $userId, string $type, ?int $categoryId, int $levelId, $questions): TestSession
    {
        return DB::transaction(function () use ($userId, $type, $categoryId, $levelId, $questions) {
            $session = TestSession::create([
                'user_id' => $userId,
                'session_type' => $type,
                'category_id' => $categoryId,
                'level_id' => $levelId,
                'total_questions' => $questions->count(),
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
    }

    private function adaptivePlacementPayload(TestSession $session, SessionAnswer $currentAnswer, int $answeredCount, string $locale): array
    {
        return [
            'data' => [
                'id' => $session->id,
                'session_type' => $session->session_type,
                'is_adaptive' => true,
                'max_items' => self::PLACEMENT_MAX_ITEMS,
                'min_items' => self::PLACEMENT_MIN_ITEMS,
                'items_answered' => $answeredCount,
                'completed_at' => $session->completed_at,
                'current_question' => array_merge($currentAnswer->question->toClientArray($locale), [
                    'answer_id' => $currentAnswer->id,
                    'answered' => $currentAnswer->answered_at !== null,
                ]),
            ],
        ];
    }

    private function sessionPayload(TestSession $session, string $locale): array
    {
        $questions = $session->answers()
            ->with('question')
            ->orderBy('id')
            ->get()
            ->map(fn (SessionAnswer $answer) => array_merge(
                $answer->question->toClientArray($locale),
                ['answer_id' => $answer->id, 'answered' => $answer->answered_at !== null]
            ));

        return [
            'data' => [
                'id' => $session->id,
                'session_type' => $session->session_type,
                'is_adaptive' => false,
                'category_id' => $session->category_id,
                'level_id' => $session->level_id,
                'total_questions' => $session->total_questions,
                'completed_at' => $session->completed_at,
                'questions' => $questions,
            ],
        ];
    }

    private function reportPayload(TestSession $session, string $locale): array
    {
        $answers = $session->answers()->with('question')->orderBy('id')->get();

        return [
            'data' => [
                'id' => $session->id,
                'session_type' => $session->session_type,
                'total_questions' => $session->total_questions,
                'correct_count' => $session->correct_count,
                'score_percent' => (float) $session->score_percent,
                'level_before_id' => $session->level_before_id,
                'level_after_id' => $session->level_after_id,
                'theta' => $session->theta,
                'theta_se' => $session->theta_se,
                'completed_at' => $session->completed_at,
                'answers' => $answers->map(fn (SessionAnswer $answer) => [
                    'answer_id' => $answer->id,
                    'question' => $answer->question->toClientArray($locale),
                    'correct_option_key' => $answer->question->correct_option_key,
                    'selected_option_key' => $answer->selected_option_key,
                    'is_correct' => $answer->is_correct,
                    'ai_feedback_text' => $answer->ai_feedback_text,
                ]),
            ],
        ];
    }
}

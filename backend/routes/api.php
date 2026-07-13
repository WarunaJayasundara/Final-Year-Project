<?php

use App\Http\Controllers\Admin\AiQuestionController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\SourceDocumentController;
use App\Http\Controllers\Admin\StudyNoteController as AdminStudyNoteController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamProfileController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GamificationController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\Sessions\MockExamController;
use App\Http\Controllers\Sessions\TestSessionController;
use App\Http\Controllers\StudyNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// --- Auth ---
// Note: /auth/google/redirect and /auth/google/callback live in routes/web.php
// instead (see comment there) - they need session support unconditionally,
// which the "web" middleware group provides and the "api" group does not for
// a top-level browser redirect coming from Google's servers.
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/locale', [AuthController::class, 'updateLocale'])->middleware('auth:sanctum');
});

Route::post('/admin/login', [AuthController::class, 'adminLogin']);

// --- Reference data (any authenticated user) ---
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/levels', [LevelController::class, 'index']);
});

// --- Admin: user & role management ---
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,super_admin'])->group(function () {
    Route::get('/users', [UserManagementController::class, 'index']);

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole']);
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    });

    // --- Admin: content management (categories, levels, questions) ---
    Route::get('/levels', [LevelController::class, 'index']);

    Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);

    Route::post('/questions/generate-visual-preview', [QuestionController::class, 'generateVisualPreview']);
    Route::apiResource('questions', QuestionController::class)->except(['create', 'edit']);
    Route::post('/questions/{question}/image', [QuestionController::class, 'uploadImage']);

    // --- Admin: research analytics ---
    Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
    Route::get('/analytics/paired-scores', [AnalyticsController::class, 'pairedScores']);
    Route::get('/analytics/paired-scores.csv', [AnalyticsController::class, 'pairedScoresCsv']);
    Route::get('/analytics/psychometrics', [AnalyticsController::class, 'psychometrics']);
    Route::post('/analytics/recalibrate', [AnalyticsController::class, 'recalibrate']);
    Route::get('/analytics/ml-overview', [AnalyticsController::class, 'mlOverview']);
    Route::get('/analytics/ml-research-reports', [AnalyticsController::class, 'mlResearchReports']);
    Route::get('/analytics/question-bank', [AnalyticsController::class, 'questionBank']);

    // --- Admin: AI question generation (draft -> human review -> promote) ---
    Route::get('/ai-questions', [AiQuestionController::class, 'index']);
    Route::post('/ai-questions/generate', [AiQuestionController::class, 'generate']);
    Route::post('/ai-questions/{aiQuestion}/approve', [AiQuestionController::class, 'approve']);
    Route::post('/ai-questions/{aiQuestion}/reject', [AiQuestionController::class, 'reject']);
    Route::post('/ai-questions/bulk-approve', [AiQuestionController::class, 'bulkApprove']);

    // --- Admin: Knowledge & Question Source Library (PDF ingestion) ---
    Route::get('/source-documents', [SourceDocumentController::class, 'index']);
    Route::post('/source-documents', [SourceDocumentController::class, 'store']);
    Route::get('/source-documents/{sourceDocument}', [SourceDocumentController::class, 'show']);
    Route::post('/source-documents/{sourceDocument}/analyze', [SourceDocumentController::class, 'analyze']);
    Route::delete('/source-documents/{sourceDocument}', [SourceDocumentController::class, 'destroy']);

    // --- Admin: feedback & ratings ---
    Route::get('/feedback', [AdminFeedbackController::class, 'index']);
    Route::get('/feedback/stats', [AdminFeedbackController::class, 'stats']);
    Route::get('/feedback/export.csv', [AdminFeedbackController::class, 'exportCsv']);
    Route::post('/feedback/{feedback}/review', [AdminFeedbackController::class, 'markReviewed']);

    // --- Admin: Study notes (theory-book -> teaching notes, draft -> human review -> publish) ---
    Route::get('/study-notes', [AdminStudyNoteController::class, 'index']);
    Route::post('/study-notes/generate', [AdminStudyNoteController::class, 'generate']);
    Route::post('/study-notes/{studyNote}/publish', [AdminStudyNoteController::class, 'publish']);
    Route::post('/study-notes/{studyNote}/reject', [AdminStudyNoteController::class, 'reject']);
});

// --- Test sessions (end user) ---
Route::prefix('sessions')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/placement/start', [TestSessionController::class, 'startPlacement']);
    Route::post('/daily/start', [TestSessionController::class, 'startDaily']);
    Route::post('/practice/start', [TestSessionController::class, 'startPractice']);
    Route::get('/{session}', [TestSessionController::class, 'show']);
    Route::post('/{session}/answers', [TestSessionController::class, 'submitAnswer']);
    Route::post('/{session}/complete', [TestSessionController::class, 'complete']);
    Route::get('/{session}/report', [TestSessionController::class, 'report']);
    Route::post('/{session}/answers/{answer}/explain', [TestSessionController::class, 'explainAnswer']);
});

// --- Mock exams (end user) - setup only; answer/complete/report lifecycle
// reuses the generic /sessions/{session}/* routes above (a mock exam is a
// TestSession like any other, just session_type='mock' with a time limit).
Route::prefix('mock-exams')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/', [MockExamController::class, 'store']);
});

// --- Dashboard (end user) ---
Route::prefix('dashboard')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/summary', [DashboardController::class, 'summary']);
    Route::get('/progress-history', [DashboardController::class, 'progressHistory']);
});

// --- Games (end user) ---
Route::prefix('games')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [GameController::class, 'index']);
    Route::post('/{code}/score', [GameController::class, 'submitScore']);
    Route::get('/{code}/scores/me', [GameController::class, 'myScores']);
});

// --- AI coach chat widget (end user) ---
Route::prefix('coach')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/chat', [CoachController::class, 'chat']);
});

// --- Exam readiness prediction + daily check-in (end user) ---
Route::prefix('readiness')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/predict', [ReadinessController::class, 'predict']);
    Route::get('/latest', [ReadinessController::class, 'latest']);
    Route::get('/history', [ReadinessController::class, 'history']);
});

// --- Government exam profile + smart study planner (end user) ---
Route::prefix('exam-profile')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [ExamProfileController::class, 'show']);
    Route::post('/', [ExamProfileController::class, 'store']);
    Route::get('/categories', [ExamProfileController::class, 'examCategories']);
    Route::get('/study-plan', [ExamProfileController::class, 'studyPlan']);
    Route::get('/history', [ExamProfileController::class, 'history']);
    Route::post('/outcome', [ExamProfileController::class, 'outcome']);
});

// --- Student feedback & ratings ---
Route::prefix('feedback')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/', [FeedbackController::class, 'store']);
    Route::get('/mine', [FeedbackController::class, 'mine']);
});

Route::prefix('checkins')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/today', [CheckinController::class, 'today']);
    Route::post('/', [CheckinController::class, 'store']);
});

// --- Study notes: the "self-learning" reading list (published only) ---
Route::prefix('study-notes')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [StudyNoteController::class, 'index']);
    Route::get('/due-today', [StudyNoteController::class, 'dueToday']);
    Route::get('/recommendation', [StudyNoteController::class, 'recommendation']);
    Route::get('/{studyNote}', [StudyNoteController::class, 'show']);
    Route::get('/{studyNote}/practice-questions', [StudyNoteController::class, 'practiceQuestions']);
    Route::post('/{studyNote}/review', [StudyNoteController::class, 'review']);
});

// --- Gamification: XP/levels, badges, missions, leaderboard (end user) ---
Route::prefix('gamification')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/summary', [GamificationController::class, 'summary']);
    Route::get('/badges', [GamificationController::class, 'badges']);
    Route::get('/missions', [GamificationController::class, 'missions']);
    Route::post('/missions/{code}/claim', [GamificationController::class, 'claimMission']);
    Route::get('/leaderboard', [GamificationController::class, 'leaderboard']);
});

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamReadinessPrediction;
use App\Models\User;
use App\Services\Analytics\ItemAnalysisService;
use App\Services\Analytics\QuestionBankStatsService;
use App\Services\Analytics\ResearchExportService;
use App\Services\Irt\RaschCalibrationService;
use App\Services\Ml\ReadinessPredictionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private ResearchExportService $research,
        private ItemAnalysisService $itemAnalysis,
        private RaschCalibrationService $calibration,
        private ReadinessPredictionService $readiness,
        private QuestionBankStatsService $bankStats,
    ) {
    }

    public function overview(Request $request)
    {
        return response()->json(['data' => $this->research->cohortOverview($request->boolean('include_demo'))]);
    }

    public function psychometrics()
    {
        return response()->json(['data' => [
            'summary' => $this->itemAnalysis->summary(),
            'category_difficulty' => $this->itemAnalysis->categoryDifficulty(),
            'discrimination' => $this->itemAnalysis->itemDiscrimination(),
        ]]);
    }

    public function recalibrate()
    {
        return response()->json(['data' => $this->calibration->calibrate()]);
    }

    public function questionBank()
    {
        return response()->json(['data' => $this->bankStats->overview()]);
    }

    public function mlOverview(Request $request)
    {
        $includeDemo = $request->boolean('include_demo');
        $demoUserIds = $includeDemo ? null : User::where('is_demo_user', true)->pluck('id');

        $latestIds = ExamReadinessPrediction::selectRaw('MAX(id) as id')
            ->when($demoUserIds, fn ($q) => $q->whereNotIn('user_id', $demoUserIds))
            ->groupBy('user_id')
            ->pluck('id');
        $latest = ExamReadinessPrediction::whereIn('id', $latestIds)->get();

        return response()->json(['data' => [
            'students_with_prediction' => $latest->count(),
            'average_readiness_percent' => $latest->count() ? round((float) $latest->avg('readiness_percent'), 1) : null,
            'label_distribution' => [
                'ready' => $latest->where('readiness_label', 'ready')->count(),
                'almost_ready' => $latest->where('readiness_label', 'almost_ready')->count(),
                'needs_improvement' => $latest->where('readiness_label', 'needs_improvement')->count(),
                'high_risk' => $latest->where('readiness_label', 'high_risk')->count(),
            ],
            'model' => $this->readiness->modelMetadata(),
        ]]);
    }

    /**
     * Bundles the three offline ML reports (evaluate.py, explain.py,
     * model_registry.py) for the admin ML Research page - each nullable
     * since a report only exists once its script has been run.
     */
    public function mlResearchReports()
    {
        return response()->json(['data' => [
            'evaluation' => $this->readiness->evaluationReport(),
            'explainability' => $this->readiness->explainabilityReport(),
            'registry' => $this->readiness->versionRegistry(),
        ]]);
    }

    public function pairedScores(Request $request)
    {
        return response()->json(['data' => $this->research->pairedScores($request->boolean('include_demo'))]);
    }

    public function pairedScoresCsv(Request $request): StreamedResponse
    {
        $rows = $this->research->pairedScores($request->boolean('include_demo'));

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['user_id', 'name', 'email', 'pre_score_percent', 'post_score_percent', 'level_start', 'level_current', 'daily_sessions_completed', 'attendance_percent']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['user_id'],
                    $row['name'],
                    $row['email'],
                    $row['pre_score_percent'],
                    $row['post_score_percent'],
                    $row['level_start'],
                    $row['level_current'],
                    $row['daily_sessions_completed'],
                    $row['attendance_percent'],
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'paired-scores.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamReadinessPrediction;
use App\Services\Analytics\ItemAnalysisService;
use App\Services\Analytics\QuestionBankStatsService;
use App\Services\Analytics\ResearchExportService;
use App\Services\Irt\RaschCalibrationService;
use App\Services\Ml\ReadinessPredictionService;
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

    public function overview()
    {
        return response()->json(['data' => $this->research->cohortOverview()]);
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

    public function mlOverview()
    {
        $latestIds = ExamReadinessPrediction::selectRaw('MAX(id) as id')->groupBy('user_id')->pluck('id');
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

    public function pairedScores()
    {
        return response()->json(['data' => $this->research->pairedScores()]);
    }

    public function pairedScoresCsv(): StreamedResponse
    {
        $rows = $this->research->pairedScores();

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['user_id', 'name', 'email', 'pre_score_percent', 'post_score_percent', 'level_start', 'level_current', 'daily_sessions_completed']);

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
                ]);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'paired-scores.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}

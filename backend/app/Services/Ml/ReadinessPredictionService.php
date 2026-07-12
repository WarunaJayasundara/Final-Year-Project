<?php

namespace App\Services\Ml;

use App\Models\ExamReadinessPrediction;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Calls the local FastAPI exam-readiness inference microservice
 * (ml-service/app.py) and persists the result as a new
 * ExamReadinessPrediction row - kept as a history (not overwritten) so the
 * dashboard can plot a readiness trend and admin analytics can look at
 * cohort trends over time. Same swappable-HTTP-call pattern as
 * GeminiAiFeedbackService, but unlike Gemini there is no "mock" fallback:
 * an unreachable ML service is a real configuration problem worth
 * surfacing (503), not something to silently paper over with a guess.
 */
class ReadinessPredictionService
{
    // PHP 8.0 doesn't support "new in initializers" (8.1+), so the default
    // Client is constructed in the body instead of the constructor signature.
    private Client $client;

    public function __construct(private FeatureExtractionService $features, ?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function predictFor(User $user): ExamReadinessPrediction
    {
        $featureVector = $this->features->extract($user);

        // The previous prediction's own feature snapshot (not the current
        // one) - sent so /predict can compute a genuine before/after delta
        // for the trend-aware plain-English explanation ("your X dropped by
        // Y%"). Omitted entirely (not just null) when this is the student's
        // first prediction, since app.py treats a missing key differently
        // from an explicit empty history.
        $previousFeatures = ExamReadinessPrediction::where('user_id', $user->id)
            ->orderByDesc('predicted_at')
            ->value('features');

        $payload = $featureVector;
        if ($previousFeatures !== null) {
            $payload['previous_features'] = $previousFeatures;
        }

        // Optional time-aware signals (see FeatureExtractionService::
        // extractTimeAware()'s docblock) - sent alongside, never merged into
        // $featureVector itself, so the persisted 'features' snapshot and
        // the classifier's input contract stay exactly the 43-value vector
        // the currently-deployed model expects. Only used by /predict to
        // derive the additive, rule-based time_management_readiness_percent.
        $timeAware = $this->features->extractTimeAware($user);
        $payload['exam_pace_gap'] = $timeAware['exam_pace_gap'];
        $payload['time_efficiency_score'] = $timeAware['time_efficiency_score'];

        $url = rtrim(config('services.ml_service.url'), '/') . '/predict';

        try {
            $response = $this->client->post($url, [
                'json' => $payload,
                'timeout' => 10,
            ]);
        } catch (GuzzleException $e) {
            Log::error('Exam readiness ML service call failed.', ['error' => $e->getMessage()]);

            throw new \RuntimeException('Exam readiness prediction service is unavailable.', previous: $e);
        }

        $body = json_decode((string) $response->getBody(), true);

        return ExamReadinessPrediction::create([
            'user_id' => $user->id,
            'features' => $featureVector,
            'readiness_percent' => $body['readiness_percent'],
            'readiness_label' => $body['readiness_label'],
            'reasons' => $body['reasons'],
            'model_version' => $body['model_version'],
            'predicted_at' => now(),
            // Additive research-grade fields (§ml-service/app.py) - all
            // optional in the response, so a caller running against an
            // older deployed model (before train_multioutput.py has ever
            // been run) still gets a valid row with these simply null.
            'risk_of_dropping_practice_probability' => $body['risk_of_dropping_practice']['probability'] ?? null,
            'at_risk_of_dropping_practice' => $body['risk_of_dropping_practice']['at_risk'] ?? null,
            'predicted_next_assessment_score' => $body['predicted_next_assessment_score'] ?? null,
            'predicted_score_change' => $body['predicted_score_change'] ?? null,
            'plain_english_explanation' => $body['plain_english_explanation'] ?? null,
            'time_management_readiness_percent' => $body['time_management_readiness_percent'] ?? null,
            'predicted_score_range' => $body['predicted_score_range'] ?? null,
        ]);
    }

    /**
     * The live model's metadata - after the research-grade upgrade this IS
     * the full model_comparison.py + Optuna HPO report (9-model screening,
     * nested-CV results), since app.py's /metadata serves whichever of
     * metadata.json / model_comparison_report.json exists.
     */
    public function modelMetadata(): ?array
    {
        return $this->fetchOptionalJson('/metadata');
    }

    /** evaluate.py's comprehensive metric suite - null until that script has been run at least once. */
    public function evaluationReport(): ?array
    {
        return $this->fetchOptionalJson('/evaluation-report');
    }

    /** explain.py's SHAP/LIME/permutation-importance/PDP report - null until that script has been run at least once. */
    public function explainabilityReport(): ?array
    {
        return $this->fetchOptionalJson('/explainability-report');
    }

    /** model_registry.py's version history (every trained version, live or archived). */
    public function versionRegistry(): ?array
    {
        return $this->fetchOptionalJson('/models');
    }

    /**
     * GET helper shared by the report-fetching methods above - all of them
     * are "nice to have" admin-dashboard data, not required for a student's
     * own prediction to work, so a service-unreachable or 404 (report not
     * yet generated) response degrades to null rather than throwing, unlike
     * predictFor()'s hard failure on an unreachable service.
     */
    private function fetchOptionalJson(string $path): ?array
    {
        try {
            $response = $this->client->get(rtrim(config('services.ml_service.url'), '/') . $path, ['timeout' => 5]);

            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::warning("Could not fetch ML service {$path}.", ['error' => $e->getMessage()]);

            return null;
        }
    }
}

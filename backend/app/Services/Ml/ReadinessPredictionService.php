<?php

namespace App\Services\Ml;

use App\Models\ExamReadinessPrediction;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

/** Calls the local FastAPI exam-readiness inference microservice. */
class ReadinessPredictionService
{
    // PHP 8.0 doesn't support "new in initializers" (8.1+), so the default
    // Client is constructed in the body instead of the constructor signature.
    private Client $client;

    public function __construct(private FeatureExtractionService $features, ?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    /** The ML service is a separate process, briefly unreachable while it restarts or loads its model. */
    private function postWithRetry(string $url, array $payload): ResponseInterface
    {
        $lastError = null;

        foreach ([0, 400_000] as $delayMicroseconds) {
            if ($delayMicroseconds > 0) {
                usleep($delayMicroseconds);
            }

            try {
                return $this->client->post($url, [
                    'json' => $payload,
                    'timeout' => 10,
                    'connect_timeout' => 3,
                ]);
            } catch (ConnectException|ServerException $e) {
                $lastError = $e;
            } catch (GuzzleException $e) {
                $lastError = $e;
                break;
            }
        }

        Log::error('Exam readiness ML service call failed.', ['error' => $lastError?->getMessage()]);

        throw new \RuntimeException('Exam readiness prediction service is unavailable.', previous: $lastError);
    }

    public function predictFor(User $user): ExamReadinessPrediction
    {
        $featureVector = $this->features->extract($user);

        // The previous prediction's own feature snapshot (not the current one).
        $previousFeatures = ExamReadinessPrediction::where('user_id', $user->id)
            ->orderByDesc('predicted_at')
            ->value('features');

        $payload = $featureVector;
        if ($previousFeatures !== null) {
            $payload['previous_features'] = $previousFeatures;
        }

        // Optional time-aware signals (see FeatureExtractionService:: extractTimeAware()'s docblock) - sent alongside.
        $timeAware = $this->features->extractTimeAware($user);
        $payload['exam_pace_gap'] = $timeAware['exam_pace_gap'];
        $payload['time_efficiency_score'] = $timeAware['time_efficiency_score'];

        $url = rtrim(config('services.ml_service.url'), '/').'/predict';

        $response = $this->postWithRetry($url, $payload);

        $body = json_decode((string) $response->getBody(), true);
        foreach (['readiness_percent', 'readiness_label', 'reasons', 'model_version'] as $required) {
            if (! is_array($body) || ! array_key_exists($required, $body)) {
                Log::error('Exam readiness ML service returned an unexpected response.', ['missing' => $required]);

                throw new \RuntimeException('Exam readiness prediction service returned an unexpected response.');
            }
        }

        return ExamReadinessPrediction::create([
            'user_id' => $user->id,
            'features' => $featureVector,
            'readiness_percent' => $body['readiness_percent'],
            'readiness_label' => $body['readiness_label'],
            'reasons' => $body['reasons'],
            'model_version' => $body['model_version'],
            'predicted_at' => now(),
            // Additive research-grade fields (§ml-service/app.py) - all optional in the response.
            'risk_of_dropping_practice_probability' => $body['risk_of_dropping_practice']['probability'] ?? null,
            'at_risk_of_dropping_practice' => $body['risk_of_dropping_practice']['at_risk'] ?? null,
            'predicted_next_assessment_score' => $body['predicted_next_assessment_score'] ?? null,
            'predicted_score_change' => $body['predicted_score_change'] ?? null,
            'plain_english_explanation' => $body['plain_english_explanation'] ?? null,
            'time_management_readiness_percent' => $body['time_management_readiness_percent'] ?? null,
            'predicted_score_range' => $body['predicted_score_range'] ?? null,
        ]);
    }

    /** The live model's metadata - after the research-grade upgrade this IS the full model_comparison.py + Optuna HPO report. */
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

    /** GET helper shared by the report-fetching methods above - all of them are "nice to have" admin-dashboard data. */
    private function fetchOptionalJson(string $path): ?array
    {
        try {
            $response = $this->client->get(rtrim(config('services.ml_service.url'), '/').$path, ['timeout' => 5]);

            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::warning("Could not fetch ML service {$path}.", ['error' => $e->getMessage()]);

            return null;
        }
    }
}

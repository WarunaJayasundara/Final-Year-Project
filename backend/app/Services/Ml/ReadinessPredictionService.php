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

        $url = rtrim(config('services.ml_service.url'), '/') . '/predict';

        try {
            $response = $this->client->post($url, [
                'json' => $featureVector,
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
        ]);
    }

    public function modelMetadata(): ?array
    {
        try {
            $response = $this->client->get(rtrim(config('services.ml_service.url'), '/') . '/metadata', ['timeout' => 5]);

            return json_decode((string) $response->getBody(), true);
        } catch (GuzzleException $e) {
            Log::warning('Could not fetch ML model metadata.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

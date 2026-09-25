<?php

namespace App\Services\QuestionBank;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/** Calls ml-service's /duplicate-check endpoint (TF-IDF + cosine similarity) as a second. */
class DuplicateDetectionService
{
    private const DEFAULT_THRESHOLD = 0.75;

    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    /** @param  string[]  $candidateTexts */
    public function isSemanticDuplicate(string $newText, array $candidateTexts, float $threshold = self::DEFAULT_THRESHOLD): bool
    {
        if ($candidateTexts === []) {
            return false;
        }

        $url = rtrim(config('services.ml_service.url'), '/').'/duplicate-check';

        try {
            $response = $this->client->post($url, [
                'json' => [
                    'new_text' => $newText,
                    'candidate_texts' => array_values($candidateTexts),
                    'threshold' => $threshold,
                ],
                'timeout' => 5,
            ]);
        } catch (GuzzleException $e) {
            Log::warning('Semantic duplicate-check service unreachable, relying on Jaccard only.', ['error' => $e->getMessage()]);

            return false;
        }

        $body = json_decode((string) $response->getBody(), true);

        return (bool) ($body['is_duplicate'] ?? false);
    }
}

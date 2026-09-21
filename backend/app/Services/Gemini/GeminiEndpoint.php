<?php

namespace App\Services\Gemini;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * Single place that builds Gemini generateContent URLs, so a retired model name
 * can never silently disable every AI feature again (a hardcoded model id was
 * retired by Google and all Gemini services quietly fell back to their mocks).
 *
 * Model names come from config (GEMINI_MODEL, GEMINI_TRANSLATION_MODEL). The
 * defaults are Google's "-latest" aliases, which always resolve to a current
 * model and are only served on the v1beta API.
 */
class GeminiEndpoint
{
    public const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /** General model: coach chat, feedback, question and note drafting. */
    public static function url(string $apiKey): string
    {
        return self::build(config('services.gemini.model'), $apiKey);
    }

    /** Higher-quality model reserved for Sinhala translation. */
    public static function translationUrl(string $apiKey): string
    {
        return self::build(config('services.gemini.translation_model'), $apiKey);
    }

    /**
     * POST to Gemini, retrying briefly on 429 (rate limit) and 503 (overloaded). Google returns both often on
     * the free tier and they usually clear within seconds, so one retry beats falling back to mock output.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException after the final attempt
     */
    public static function post(Client $client, string $url, array $options, int $attempts = 3): ResponseInterface
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $client->post($url, $options);
            } catch (BadResponseException $e) {
                $status = $e->getResponse()->getStatusCode();
                if ($attempt >= $attempts || ! in_array($status, [429, 503], true)) {
                    throw $e;
                }
                usleep($attempt * 1_000_000);
            }
        }
    }

    /** Guzzle exception messages contain the full request URL, including the API key. Never log it. */
    public static function redact(string $message): string
    {
        return preg_replace('/([?&]key=)[A-Za-z0-9._-]+/', '$1[redacted]', $message) ?? $message;
    }

    private static function build(string $model, string $apiKey): string
    {
        return self::BASE.$model.':generateContent?key='.$apiKey;
    }
}

<?php

namespace App\Services\QuestionBank;

use App\Services\Gemini\GeminiEndpoint;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/** Drafts Sinhala for an English question through Gemini, grounded in the project's reviewed terminology glossary. */
class SinhalaTranslationService
{
    private const MAX_FIELDS = 14;

    private const MAX_CHARS = 2000;

    private const MAX_ATTEMPTS = 3;

    private Client $client;

    private SinhalaTextGuard $guard;

    public function __construct(?Client $client = null, ?SinhalaTextGuard $guard = null)
    {
        $this->client = $client ?? new Client();
        $this->guard = $guard ?? new SinhalaTextGuard();
    }

    /**
     * @param  array<string,string>  $fields  key => English text (keys such as "question", "option_A", "explanation")
     * @return array{translations: array<string,string>, issues: array<string,array>}
     *
     * @throws SinhalaTranslationUnavailable
     */
    public function translate(array $fields): array
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            throw new SinhalaTranslationUnavailable('No Gemini API key is configured, so automatic translation is unavailable.');
        }

        $fields = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $fields), fn ($v) => $v !== '');
        if ($fields === [] || count($fields) > self::MAX_FIELDS) {
            throw new SinhalaTranslationUnavailable('Provide between 1 and '.self::MAX_FIELDS.' English fields to translate.');
        }
        foreach ($fields as $value) {
            if (mb_strlen($value) > self::MAX_CHARS) {
                throw new SinhalaTranslationUnavailable('One of the fields is too long to translate.');
            }
        }

        $payload = [
            'json' => [
                'contents' => [['role' => 'user', 'parts' => [['text' => $this->prompt($fields)]]]],
                'generationConfig' => ['temperature' => 0.2, 'responseMimeType' => 'application/json'],
            ],
            'timeout' => 45,
        ];

        $parsed = null;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $this->client->post(GeminiEndpoint::translationUrl($apiKey), $payload);
                $body = json_decode((string) $response->getBody(), true);
                $parsed = json_decode($body['candidates'][0]['content']['parts'][0]['text'] ?? '', true);
                break;
            } catch (RequestException $e) {
                $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
                // Gemini answers 503 ("high demand") and 429 (rate limit) for short spikes: worth a brief retry.
                if (in_array($status, [429, 500, 502, 503, 504], true) && $attempt < self::MAX_ATTEMPTS) {
                    usleep(1500000 * $attempt);

                    continue;
                }
                $busy = in_array($status, [429, 503], true);

                throw new SinhalaTranslationUnavailable($busy
                    ? 'The translation service is busy right now. Wait a few seconds and try again, or type the Sinhala manually.'
                    : 'The translation service could not be reached. Try again, or type the Sinhala manually.');
            } catch (\Throwable $e) {
                throw new SinhalaTranslationUnavailable('The translation service could not be reached. Try again, or type the Sinhala manually.');
            }
        }

        if (! is_array($parsed)) {
            throw new SinhalaTranslationUnavailable('The translation service returned an unusable answer. Try again, or type the Sinhala manually.');
        }

        $translations = [];
        $issues = [];
        foreach ($fields as $key => $english) {
            $si = isset($parsed[$key]) && is_string($parsed[$key]) ? trim($parsed[$key]) : '';
            if ($si === '') {
                continue;
            }
            $translations[$key] = $si;
            $found = $this->guard->inspect($si, $english, ! str_starts_with($key, 'option_'));
            if ($found !== []) {
                $issues[$key] = $found;
            }
        }

        if ($translations === []) {
            throw new SinhalaTranslationUnavailable('No usable translation was produced. Try again, or type the Sinhala manually.');
        }

        return ['translations' => $translations, 'issues' => $issues];
    }

    /** @param  array<string,string>  $fields */
    private function prompt(array $fields): string
    {
        $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $terms = $this->glossaryTerms();

        return <<<PROMPT
        You translate competitive-exam aptitude questions from English into Sinhala for adult Sri Lankan exam candidates (age 20-30).

        Rules:
        - Write clear, natural, formal-but-simple Sinhala that a candidate can understand on first reading. Prefer common everyday words over rare literary ones.
        - Keep every number, letter label (A, B, C), symbol and unit exactly as in the English. Use Arabic numerals (63, not words).
        - Keep personal names as they are. Do not add, remove or explain anything.
        - Keep the meaning identical; do not change which answer is correct.
        - Technical acronyms (AND, OR, XOR, IQ) stay in English.
        - Use these reviewed terms where they apply: {$terms}

        Return ONLY a JSON object with exactly the same keys as the input, each value being the Sinhala translation of that value.

        Input:
        {$json}
        PROMPT;
    }

    private function glossaryTerms(): string
    {
        $path = base_path('resources/sinhala_glossary.json');
        if (! file_exists($path)) {
            return '(none)';
        }

        $glossary = json_decode(file_get_contents($path), true) ?: [];
        $pairs = [];
        foreach ($glossary as $domain => $entries) {
            if (! is_array($entries) || $domain === '_meta') {
                continue;
            }
            foreach ($entries as $entry) {
                if (isset($entry['en'], $entry['si'])) {
                    $pairs[$entry['en']] = $entry['si'];
                }
            }
        }

        return $pairs === [] ? '(none)' : implode('; ', array_map(fn ($en, $si) => "{$en} = {$si}", array_keys($pairs), $pairs));
    }
}

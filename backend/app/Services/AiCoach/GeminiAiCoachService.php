<?php

namespace App\Services\AiCoach;

use App\Contracts\AiCoachServiceInterface;
use App\Models\User;
use App\Services\Analytics\IqScoreService;
use App\Services\Analytics\StreakService;
use App\Services\Analytics\StudentContextService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Real Gemini-backed coach. Not active until AI_COACH_DRIVER=gemini and
 * GEMINI_API_KEY are set in .env - see AppServiceProvider::register(). Falls
 * back to MockAiCoachService if the API call fails, so a missing/invalid key
 * never breaks the chat widget. Every call rebuilds the student's context
 * fresh from the database, so answers stay grounded in current data without
 * any fine-tuning or persisted "memory" of the student.
 */
class GeminiAiCoachService implements AiCoachServiceInterface
{
    private const MODEL = 'gemini-2.5-flash';

    private StudentContextService $context;

    private Client $client;

    private MockAiCoachService $fallback;

    // PHP 8.0 (this project's XAMPP-pinned version) doesn't support "new in
    // initializers" (PHP 8.1+), so defaults are resolved in the body instead
    // of the constructor signature.
    public function __construct(
        StudentContextService $context,
        ?Client $client = null,
        ?MockAiCoachService $fallback = null
    ) {
        $this->context = $context;
        $this->client = $client ?? new Client();
        $this->fallback = $fallback ?? new MockAiCoachService(new StudentContextService(new IqScoreService(), new StreakService()));
    }

    public function chat(User $user, string $message, array $history, string $locale): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return $this->fallback->chat($user, $message, $history, $locale);
        }

        try {
            $ctx = $this->context->build($user);

            $contents = [
                ['role' => 'user', 'parts' => [['text' => $this->buildSystemPrompt($ctx, $locale)]]],
                ['role' => 'model', 'parts' => [['text' => $locale === 'si' ? 'තේරුණා.' : 'Understood.']]],
            ];

            foreach ($history as $turn) {
                $contents[] = [
                    'role' => $turn['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $turn['content']]],
                ];
            }

            $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

            $response = $this->client->post(
                sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s', self::MODEL, $apiKey),
                [
                    'json' => ['contents' => $contents],
                    'timeout' => 15,
                ]
            );

            $body = json_decode((string) $response->getBody(), true);
            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

            return $text ?: $this->fallback->chat($user, $message, $history, $locale);
        } catch (\Throwable $e) {
            Log::warning('Gemini AI coach call failed, falling back to mock coach.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback->chat($user, $message, $history, $locale);
        }
    }

    private function buildSystemPrompt(array $ctx, string $locale): string
    {
        $languageName = $locale === 'si' ? 'Sinhala' : 'English';
        $levelName = $locale === 'si' ? $ctx['level_name_si'] : $ctx['level_name_en'];
        $weakest = $ctx['weakest_category'];
        $weakestName = $weakest ? ($locale === 'si' ? $weakest['name_si'] : $weakest['name_en']) : 'unknown';
        $iq = $ctx['iq_estimate']['iq_score'] ?? 'not yet available';

        return <<<PROMPT
        You are HelaIQ's friendly cognitive-training coach for a Sri Lankan student aged 20-30
        preparing for competitive exams. Always reply in {$languageName}, in 2-5 short sentences,
        encouraging and specific - never generic filler.

        Student: {$ctx['name']}
        Current level: {$levelName}
        Practice streak: {$ctx['streak_days']} day(s)
        Sessions completed: {$ctx['sessions_completed']}
        Recent average score: {$ctx['recent_avg_score_percent']}%
        Weakest category: {$weakestName}
        Estimated IQ score: {$iq}

        Ground every answer in this real data - do not invent stats. If the student asks what to
        practice, recommend their weakest category. If they ask about games, suggest one that
        trains that category (Memory Match, Sequence Puzzle, or Mental Math Rush).
        PROMPT;
    }
}

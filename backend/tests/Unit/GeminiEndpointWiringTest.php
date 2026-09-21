<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\IqLevel;
use App\Services\AiQuestionGeneration\GeminiAiQuestionGeneratorService;
use App\Services\Gemini\GeminiEndpoint;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * Regression tests for a silent failure: four Gemini services referenced GeminiEndpoint without
 * importing it, every call threw "class not found", and the services' catch-all fallback quietly
 * served mock output instead. The mock-driver tests could not see this.
 */
class GeminiEndpointWiringTest extends TestCase
{
    public function test_every_service_that_uses_gemini_endpoint_imports_it()
    {
        $root = dirname(__DIR__, 2).'/app/Services';
        $checked = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! str_contains($source, 'GeminiEndpoint::')) {
                continue;
            }

            $checked++;
            $inSameNamespace = str_contains($source, 'namespace App\\Services\\Gemini;');
            $imported = str_contains($source, 'use App\\Services\\Gemini\\GeminiEndpoint;');
            $this->assertTrue($inSameNamespace || $imported, $file->getFilename().' uses GeminiEndpoint without importing it.');
        }

        $this->assertGreaterThanOrEqual(5, $checked);
    }

    public function test_question_generator_calls_the_configured_gemini_model_and_returns_its_question()
    {
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'gemini-test-model']);

        $question = [
            'question_text_en' => 'A shop sells pens at Rs. 12 each. What is the cost of 7 pens?',
            'question_text_si' => 'කඩයක පෑන් එකක් රු. 12කට විකුණයි. පෑන් 7ක මිල කීයද?',
            'options' => [
                ['key' => 'A', 'text_en' => '84', 'text_si' => '84'],
                ['key' => 'B', 'text_en' => '74', 'text_si' => '74'],
                ['key' => 'C', 'text_en' => '94', 'text_si' => '94'],
                ['key' => 'D', 'text_en' => '82', 'text_si' => '82'],
            ],
            'correct_option_key' => 'A',
            'explanation_en' => '12 x 7 = 84.',
            'explanation_si' => '12 x 7 = 84.',
            'difficulty_weight' => 2,
            'estimated_time_seconds' => 30,
        ];
        $body = json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode($question)]]]]]]);

        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $body)]));
        $stack->push(Middleware::history($history));

        $service = new GeminiAiQuestionGeneratorService(new Client(['handler' => $stack]));
        $result = $service->generate(new Category(['code' => 'numerical_ability', 'name_en' => 'Numerical Ability']), new IqLevel(['level_number' => 2]), null, []);

        $this->assertCount(1, $history, 'The Gemini endpoint must actually be called.');
        $this->assertStringContainsString('gemini-test-model:generateContent', (string) $history[0]['request']->getUri());
        $this->assertSame($question['question_text_en'], $result['question_text_en'], 'The Gemini answer, not the mock fallback, must be returned.');
    }

    public function test_logged_error_messages_never_contain_the_api_key()
    {
        $message = 'Client error: `POST https://generativelanguage.googleapis.com/v1beta/models/m:generateContent?key=AQ.secret-Key_123` resulted in a `429`';

        $redacted = GeminiEndpoint::redact($message);

        $this->assertStringNotContainsString('AQ.secret-Key_123', $redacted);
        $this->assertStringContainsString('key=[redacted]', $redacted);
    }

    public function test_post_retries_once_on_overload_but_not_on_client_errors()
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(503, [], '{}'), new Response(200, [], '{"ok":true}')]));
        $stack->push(Middleware::history($history));

        $response = GeminiEndpoint::post(new Client(['handler' => $stack]), 'https://example.test/x', ['json' => []]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(2, $history, 'A 503 must be retried.');

        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(400, [], '{}'), new Response(200, [], '{}')]));
        $stack->push(Middleware::history($history));

        try {
            GeminiEndpoint::post(new Client(['handler' => $stack]), 'https://example.test/x', ['json' => []]);
            $this->fail('A 400 must not be retried or swallowed.');
        } catch (\GuzzleHttp\Exception\BadResponseException) {
            $this->assertCount(1, $history);
        }
    }
}

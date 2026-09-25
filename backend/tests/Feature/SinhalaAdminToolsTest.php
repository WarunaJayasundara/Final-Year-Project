<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IqLevel;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionBank\SinhalaTranslationService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin Sinhala tooling: integrity check, machine-drafted translation and the save-time rejection of corrupted Sinhala. */
class SinhalaAdminToolsTest extends TestCase
{
    private ?User $admin = null;

    private array $createdQuestionIds = [];

    protected function tearDown(): void
    {
        Question::whereIn('id', $this->createdQuestionIds)->delete();
        if ($this->admin) {
            $this->admin->delete();
        }

        parent::tearDown();
    }

    private function actAsAdmin(): self
    {
        $this->admin = User::create([
            'name' => 'Sinhala Tools Test Admin',
            'email' => 'sinhala-tools-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'admin',
            'locale' => 'en',
        ]);

        return $this->actingAs($this->admin, 'web');
    }

    private function fakeGemini(array $translations): void
    {
        $body = json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode($translations, JSON_UNESCAPED_UNICODE)]]]]]]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], $body)]))]);
        $this->app->bind(SinhalaTranslationService::class, fn () => new SinhalaTranslationService($client));
    }

    public function test_check_endpoint_flags_corrupted_sinhala_as_an_error()
    {
        $response = $this->actAsAdmin()->postJson('/api/admin/sinhala/check', [
            'fields' => ['question' => ['si' => "දින \u{0D15} ගණන", 'en' => 'How many days?']],
        ]);

        $response->assertOk();
        $this->assertSame('error', $response->json('data.issues.question.0.severity'));
        $this->assertSame('stray_script', $response->json('data.issues.question.0.code'));
    }

    public function test_check_endpoint_accepts_clean_sinhala_and_short_options()
    {
        $response = $this->actAsAdmin()->postJson('/api/admin/sinhala/check', [
            'fields' => [
                'question' => ['si' => 'දෙදෙනා එකට වැඩ කළහොත් දින කීයද?', 'en' => 'How many days together?'],
                'option_A' => ['si' => '42', 'en' => '42', 'is_option' => true],
            ],
        ]);

        $response->assertOk();
        $this->assertSame([], $response->json('data.issues'));
    }

    public function test_translate_returns_drafts_and_runs_them_through_the_guard()
    {
        config(['services.gemini.api_key' => 'test-key']);
        $this->fakeGemini(['question' => 'දෙදෙනා එකට වැඩ කළහොත් දින කීයද?', 'option_A' => '42']);

        $response = $this->actAsAdmin()->postJson('/api/admin/sinhala/translate', [
            'fields' => ['question' => 'How many days together?', 'option_A' => '42'],
        ]);

        $response->assertOk();
        $this->assertSame('42', $response->json('data.translations.option_A'));
        $this->assertNotEmpty($response->json('data.translations.question'));
        $this->assertSame([], $response->json('data.issues'));
    }

    public function test_translate_retries_a_temporary_503_then_succeeds_and_reports_busy_when_it_persists()
    {
        config(['services.gemini.api_key' => 'test-key']);
        $ok = new Response(200, [], json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode(['question' => 'දින කීයද?'], JSON_UNESCAPED_UNICODE)]]]]]]));

        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(503), $ok]))]);
        $this->app->bind(SinhalaTranslationService::class, fn () => new SinhalaTranslationService($client));
        $this->actAsAdmin()->postJson('/api/admin/sinhala/translate', ['fields' => ['question' => 'How many days?']])
            ->assertOk()
            ->assertJsonPath('data.translations.question', 'දින කීයද?');

        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(503), new Response(503), new Response(503)]))]);
        $this->app->bind(SinhalaTranslationService::class, fn () => new SinhalaTranslationService($client));
        $this->postJson('/api/admin/sinhala/translate', ['fields' => ['question' => 'How many days?']])
            ->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'busy'));
    }

    public function test_translate_reports_service_unavailable_without_an_api_key_and_never_invents_text()
    {
        config(['services.gemini.api_key' => null]);

        $this->actAsAdmin()->postJson('/api/admin/sinhala/translate', [
            'fields' => ['question' => 'How many days together?'],
        ])->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_translate_flags_a_draft_that_came_back_untranslated()
    {
        config(['services.gemini.api_key' => 'test-key']);
        $this->fakeGemini(['question' => 'How many days together?']);

        $response = $this->actAsAdmin()->postJson('/api/admin/sinhala/translate', [
            'fields' => ['question' => 'How many days together?'],
        ]);

        $response->assertOk();
        $codes = array_column($response->json('data.issues.question'), 'code');
        $this->assertContains('no_sinhala', $codes);
    }

    public function test_saving_a_question_with_corrupted_sinhala_is_rejected()
    {
        $category = Category::where('code', 'numerical_ability')->firstOrFail();
        $level = IqLevel::where('level_number', 2)->firstOrFail();

        $response = $this->actAsAdmin()->postJson('/api/admin/questions', [
            'category_id' => $category->id,
            'level_id' => $level->id,
            'question_type' => 'mcq_text',
            'question_text_en' => 'What is 2 plus 2?',
            'question_text_si' => "2 සහ 2 \u{0D15} එකතුව කුමක්ද?",
            'options' => [
                ['key' => 'A', 'text_en' => '4', 'text_si' => '4'],
                ['key' => 'B', 'text_en' => '5', 'text_si' => '5'],
            ],
            'correct_option_key' => 'A',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['question_text_si']);
        $this->assertSame(0, Question::where('question_text_en', 'What is 2 plus 2?')->count());
    }
}

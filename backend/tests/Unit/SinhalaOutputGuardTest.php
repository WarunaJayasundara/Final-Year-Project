<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\AiFeedback\GeminiAiFeedbackService;
use App\Services\Gemini\SinhalaStyle;
use App\Services\QuestionBank\SinhalaTextGuard;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * A model's Sinhala can arrive with letters from other scripts (Korean, Amharic, Kannada, ...). That must
 * never reach a student, whichever service produced it.
 */
class SinhalaOutputGuardTest extends TestCase
{
    public function test_guard_rejects_letters_from_scripts_models_slip_into_sinhala()
    {
        $guard = new SinhalaTextGuard();

        foreach (['ගිණුමක් නොමැ니까?', 'මෙය ይህ කිරීමට', 'අಮಗೆ පිටුව', 'සමාලෝචනය абв', 'ප්‍රශ්නය 漢字'] as $bad) {
            $this->assertTrue(SinhalaTextGuard::hasError($guard->inspect($bad, null, true)), "Should reject: {$bad}");
        }
    }

    public function test_guard_still_accepts_normal_sinhala_and_maths_symbols()
    {
        $guard = new SinhalaTextGuard();

        foreach (['ඔබේ ඇස්තමේන්තුගත IQ ලකුණු 112 කි.', 'හැකියා ව්‍යාප්තිය (θ) සහ π අගය', 'දින 3ක අඛණ්ඩතාව - 100% ලකුණු ± 5'] as $good) {
            $this->assertFalse(SinhalaTextGuard::hasError($guard->inspect($good, null, true)), "Should accept: {$good}");
        }
    }

    public function test_style_rules_name_the_preferred_terms()
    {
        $rules = SinhalaStyle::rules();

        $this->assertStringContainsString('උපකරණ පුවරුව', $rules);
        $this->assertStringContainsString('පුරෝකථනය', $rules);
        $this->assertTrue(SinhalaStyle::acceptable('ඔබ මෙම ප්‍රශ්නය නිවැරදිව විසඳා ඇත.'));
        $this->assertFalse(SinhalaStyle::acceptable('ඔබ මෙම ප්‍රශ්නය 지 විසඳා ඇත.'));
    }

    public function test_feedback_with_corrupted_sinhala_falls_back_and_clean_sinhala_is_kept()
    {
        config(['services.gemini.api_key' => 'test-key']);
        $question = new Question([
            'question_text_en' => 'What is 4 + 3?',
            'question_text_si' => '4 + 3 කීයද?',
            'options' => [
                ['key' => 'A', 'text_en' => '7', 'text_si' => '7'],
                ['key' => 'B', 'text_en' => '8', 'text_si' => '8'],
            ],
            'correct_option_key' => 'A',
            'explanation_en' => '4 + 3 = 7.',
            'explanation_si' => '4 + 3 = 7.',
        ]);

        $reply = fn (string $text) => new Response(200, [], json_encode(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]));

        $bad = new GeminiAiFeedbackService(new Client(['handler' => HandlerStack::create(new MockHandler([$reply('පිළිතුර 7 වේ 지')]))]));
        $this->assertStringNotContainsString('지', $bad->explainAnswer($question, 'B', 'si'));

        $clean = 'පිළිතුර 7 වේ, මන්ද 4 සහ 3 එකතු කළ විට 7 ලැබේ.';
        $good = new GeminiAiFeedbackService(new Client(['handler' => HandlerStack::create(new MockHandler([$reply($clean)]))]));
        $this->assertSame($clean, $good->explainAnswer($question, 'B', 'si'));
    }
}

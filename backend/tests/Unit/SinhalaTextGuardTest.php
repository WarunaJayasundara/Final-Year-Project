<?php

namespace Tests\Unit;

use App\Services\QuestionBank\SinhalaSemanticValidationService;
use App\Services\QuestionBank\SinhalaTextGuard;
use PHPUnit\Framework\TestCase;

class SinhalaTextGuardTest extends TestCase
{
    private function codes(string $text, ?string $english = null, bool $requireSinhala = true): array
    {
        return array_column((new SinhalaTextGuard())->inspect($text, $english, $requireSinhala), 'code');
    }

    public function test_clean_sinhala_has_no_issues(): void
    {
        $this->assertSame([], $this->codes('දෙදෙනා එකට වැඩ කළහොත් දින 63ක් ගතවේද?'));
    }

    public function test_stray_indic_script_is_an_error(): void
    {
        // U+0D15 is a Malayalam letter: the corruption class seen in this project before.
        $this->assertContains('stray_script', $this->codes("දින \u{0D15} ගණන"));
        $this->assertContains('stray_script', $this->codes("දින \u{FFFD} ගණන"));
    }

    public function test_orphaned_vowel_sign_is_an_error(): void
    {
        $this->assertContains('orphan_sign', $this->codes("වැඩ \u{0DCF}ක් ගතවේ"));
    }

    public function test_sentence_without_sinhala_letters_is_an_error_but_an_option_may_be_a_number_or_loanword(): void
    {
        $this->assertContains('no_sinhala', $this->codes('How many days?'));
        $this->assertSame([], $this->codes('42', null, false));
        $this->assertSame([], $this->codes('XOR', null, false));
    }

    public function test_untranslated_copy_of_english_is_a_warning_not_an_error(): void
    {
        $issues = (new SinhalaTextGuard())->inspect('How many days?', 'How many days?');

        $this->assertContains('same_as_english', array_column($issues, 'code'));
        $this->assertTrue(SinhalaTextGuard::hasError($issues)); // no_sinhala is still an error
    }

    public function test_draft_validator_no_longer_auto_approves_corrupted_sinhala(): void
    {
        $result = (new SinhalaSemanticValidationService())->validate(
            'What is 12 plus 30?',
            "මතකය 12 සහ 30 \u{0D15} එකතුව කුමක්ද?",
            correctOptionKey: 'A',
        );

        $this->assertSame('needs_review', $result['sinhala_review_status']);
    }
}

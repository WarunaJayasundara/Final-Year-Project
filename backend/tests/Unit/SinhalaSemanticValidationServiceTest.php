<?php

namespace Tests\Unit;

use App\Services\QuestionBank\SinhalaSemanticValidationService;
use PHPUnit\Framework\TestCase;

class SinhalaSemanticValidationServiceTest extends TestCase
{
    private SinhalaSemanticValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SinhalaSemanticValidationService();
    }

    public function test_matching_numbers_and_full_length_text_is_approved(): void
    {
        $result = $this->service->validate(
            'What is 12 plus 30?',
            'මතකය 12 සහ 30 එකතුව කුමක්ද?',
            options: [
                ['key' => 'A', 'text_en' => '42', 'text_si' => '42'],
                ['key' => 'B', 'text_en' => '40', 'text_si' => '40'],
            ],
            correctOptionKey: 'A',
        );

        $this->assertSame('approved', $result['sinhala_review_status']);
        $this->assertSame(1.0, $result['semantic_equivalence_score']);
    }

    public function test_missing_number_in_sinhala_text_is_flagged(): void
    {
        $result = $this->service->validate(
            'What is 12 plus 30?',
            'මෙම ප්‍රශ්නයේ පිළිතුර කුමක්ද?',
            options: null,
            correctOptionKey: 'A',
        );

        $this->assertSame('needs_review', $result['sinhala_review_status']);
        $this->assertContains('Numbers appearing in the English text are not all present in the Sinhala text.', $result['notes']);
    }

    public function test_empty_sinhala_text_is_flagged(): void
    {
        $result = $this->service->validate('What comes next?', '', options: null, correctOptionKey: 'A');

        $this->assertSame('needs_review', $result['sinhala_review_status']);
        $this->assertLessThan(1.0, $result['semantic_equivalence_score']);
    }

    public function test_mismatched_option_counts_are_flagged(): void
    {
        $result = $this->service->validate(
            'Pick the odd one out.',
            'වෙනස් වූ අයිතමය සොයාගන්න.',
            options: [
                ['key' => 'A', 'text_en' => 'Cat', 'text_si' => 'පූසා'],
                ['key' => 'B', 'text_en' => 'Dog', 'text_si' => ''],
            ],
            correctOptionKey: 'A',
        );

        $this->assertContains('English and Sinhala option counts do not match.', $result['notes']);
    }

    public function test_missing_correct_option_key_is_flagged(): void
    {
        $result = $this->service->validate('Pick one.', 'එකක් තෝරන්න.', options: null, correctOptionKey: null);

        $this->assertContains('No correct_option_key set.', $result['notes']);
    }
}

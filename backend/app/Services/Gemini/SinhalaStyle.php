<?php

namespace App\Services\Gemini;

use App\Services\QuestionBank\SinhalaTextGuard;

/** The product's Sinhala voice, as a block of instructions for any prompt that asks a model to write Sinhala. */
class SinhalaStyle
{
    /** Instructions to append to a prompt whose answer is Sinhala. */
    public static function rules(): string
    {
        return <<<'RULES'
        SINHALA STYLE (follow exactly):
        - Formal, warm, written Sinhala. Address the learner as "ඔබ". Never use spoken endings such as කළා, කරනවා, වෙනවා, සිටිනවා, and never the spoken word "මේ" (use "මෙම").
        - Use natural idiomatic Sinhala, not a word-for-word copy of English. Keep every fact and number.
        - Use Sinhala script only (plus digits 0-9, %, and the loanwords IQ, AI, XP, HelaIQ). No letters from any other script.
        - Preferred terms: dashboard = උපකරණ පුවරුව; practice = අභ්‍යාසය; placement test = මට්ටම් නිර්ණය පරීක්ෂණය; mock exam = ආදර්ශ විභාගය;
          prediction = පුරෝකථනය; readiness = සූදානම; level = මට්ටම; progress = ප්‍රගතිය; streak = දින අඛණ්ඩතාව; study plan = අධ්‍යයන සැලැස්ම;
          category = ප්‍රවර්ගය; area of skill = අංශය; badge = පදක්කම; game = ක්‍රීඩාව; logical reasoning = තාර්කික චින්තනය.
        RULES;
    }

    /** False when the text contains corruption that must never reach a student. */
    public static function acceptable(string $text): bool
    {
        return ! SinhalaTextGuard::hasError((new SinhalaTextGuard())->inspect($text, null, true));
    }
}

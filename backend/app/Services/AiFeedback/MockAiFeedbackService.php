<?php

namespace App\Services\AiFeedback;

use App\Contracts\AiFeedbackServiceInterface;
use App\Models\Question;

/**
 * Rule-based explanation generator used until a Gemini API key is configured.
 * Builds a locale-aware explanation from the question's authored explanation
 * fields plus a small templated wrapper, so every question always has usable
 * feedback even without a live AI call.
 */
class MockAiFeedbackService implements AiFeedbackServiceInterface
{
    public function explainAnswer(Question $question, string $selectedOptionKey, string $locale): string
    {
        $options = collect($question->options)->keyBy('key');
        $selected = $options->get($selectedOptionKey);
        $correct = $options->get($question->correct_option_key);

        $selectedText = $selected["text_{$locale}"] ?? $selected['text_en'] ?? $selectedOptionKey ?? '?';
        $correctText = $correct["text_{$locale}"] ?? $correct['text_en'] ?? $question->correct_option_key;

        $explanation = $locale === 'si'
            ? ($question->explanation_si ?: $question->explanation_en)
            : ($question->explanation_en ?: $question->explanation_si);

        $isCorrect = $selectedOptionKey === $question->correct_option_key;

        if ($locale === 'si') {
            if ($isCorrect) {
                return "නිවැරදියි! ඔබ තෝරාගත් \"{$selectedText}\" නිවැරදි පිළිතුරයි. {$explanation}";
            }

            return "ඔබ තෝරාගත්තේ \"{$selectedText}\" නමුත් නිවැරදි පිළිතුර \"{$correctText}\" වේ. {$explanation} ඊළඟ වතාවේ පිළිතුරු දීමට පෙර රටාව හෝ ප්‍රශ්නය නැවත හොඳින් කියවන්න.";
        }

        if ($isCorrect) {
            return "Correct! You selected \"{$selectedText}\", which is the right answer. {$explanation}";
        }

        return "You selected \"{$selectedText}\", but the correct answer is \"{$correctText}\". {$explanation} Tip: re-read the question or pattern carefully before answering next time.";
    }
}

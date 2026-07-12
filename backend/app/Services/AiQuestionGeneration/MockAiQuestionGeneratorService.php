<?php

namespace App\Services\AiQuestionGeneration;

use App\Contracts\AiQuestionGeneratorServiceInterface;
use App\Models\Category;
use App\Models\IqLevel;

/**
 * Rule-based question generator used until a Gemini API key is configured -
 * same "works today without a key, real intelligence behind a swappable
 * driver" pattern as MockAiFeedbackService. Produces a genuinely valid,
 * scored MCQ per category (not placeholder text), so the whole
 * generate -> duplicate-check -> admin-review -> promote pipeline is
 * exercisable end-to-end without any external dependency.
 *
 * The Sinhala text below is deliberately kept to short, simple phrases
 * (numbers/letters embedded in a fixed sentence frame) rather than long
 * composed sentences, since this is the offline fallback path - real,
 * naturally-phrased Sinhala generation is what GeminiAiQuestionGeneratorService
 * (a live LLM) is for.
 */
class MockAiQuestionGeneratorService implements AiQuestionGeneratorServiceInterface
{
    public function generate(Category $category, IqLevel $level, ?string $examCategoryLabel, array $avoidQuestionTexts, ?string $sourceContext = null): array
    {
        // The mock generator's fixed archetype templates can't genuinely
        // incorporate arbitrary source-document content (that needs a real
        // LLM reading the material - see GeminiAiQuestionGeneratorService).
        // $sourceContext is accepted for interface compatibility so the
        // admin PDF-ingestion flow works end-to-end even without a Gemini
        // key configured, but is intentionally not used to fabricate a
        // false impression of document-grounded generation here.
        unset($sourceContext);
        $difficulty = min(3, max(1, (int) ceil($level->level_number / 2)));

        return match ($category->code) {
            'numerical_ability' => $this->numerical($difficulty),
            'logical_reasoning' => $this->logical($difficulty),
            'memory' => $this->memory($difficulty),
            'attention' => $this->attention($difficulty),
            default => $this->spatialPattern($difficulty),
        };
    }

    private function numerical(int $difficulty): array
    {
        $a = random_int(2, 8 * $difficulty);
        $b = random_int(2, 6 * $difficulty);
        $op = $difficulty >= 3 ? ['+', '-', '*'][random_int(0, 2)] : ['+', '-'][random_int(0, 1)];
        $answer = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
        };
        $opSymbol = $op === '*' ? '×' : $op;

        return $this->buildMcq(
            questionEn: "What is {$a} {$opSymbol} {$b}?",
            questionSi: "{$a} {$opSymbol} {$b} = ?",
            correctAnswer: (string) $answer,
            distractors: [(string) ($answer + random_int(1, 5)), (string) max(0, $answer - random_int(1, 5)), (string) ($answer + random_int(6, 10))],
            explanationEn: "{$a} {$opSymbol} {$b} = {$answer}.",
            explanationSi: "{$a} {$opSymbol} {$b} = {$answer}.",
            difficulty: $difficulty,
        );
    }

    private function logical(int $difficulty): array
    {
        // A deliberately varied template pool - index 3 is always the odd
        // one out. More templates means fewer within-batch collisions with
        // QuestionDraftService's duplicate check, which compares the full
        // question text (see the note on question_text_en below).
        $groups = [
            [['Apple', 'Banana', 'Mango', 'Carrot'], ['Apple', 'Banana', 'Mango', 'Carrot'], 3],
            [['Circle', 'Square', 'Triangle', 'Red'], ['Circle', 'Square', 'Triangle', 'Red'], 3],
            [['Dog', 'Cat', 'Cow', 'Chair'], ['Dog', 'Cat', 'Cow', 'Chair'], 3],
            [['Hammer', 'Wrench', 'Pliers', 'Bread'], ['Hammer', 'Wrench', 'Pliers', 'Bread'], 3],
            [['Piano', 'Guitar', 'Violin', 'Ladder'], ['Piano', 'Guitar', 'Violin', 'Ladder'], 3],
            [['Sun', 'Moon', 'Star', 'Cup'], ['Sun', 'Moon', 'Star', 'Cup'], 3],
        ];
        $keys = ['A', 'B', 'C', 'D'];
        [$wordsEn, $wordsSi, $oddIndex] = $groups[array_rand($groups)];

        $order = range(0, 3);
        shuffle($order);

        $options = [];
        $correctKey = 'A';
        foreach ($order as $i => $originalIndex) {
            $key = $keys[$i];
            $options[] = ['key' => $key, 'text_en' => $wordsEn[$originalIndex], 'text_si' => $wordsSi[$originalIndex]];
            if ($originalIndex === $oddIndex) {
                $correctKey = $key;
            }
        }

        // The word list is embedded in the question text itself (not left
        // implicit in the options alone) - this makes the text
        // self-contained AND gives QuestionDraftService's text-based
        // duplicate check something meaningful to compare, since a fixed
        // generic stem ("Which word does not belong?") would be identical
        // across every group and defeat duplicate detection entirely.
        $wordListEn = implode(', ', $wordsEn);

        return [
            'question_text_en' => "Which word does not belong with the others: {$wordListEn}?",
            'question_text_si' => "Which word does not belong with the others: {$wordListEn}?",
            'options' => $options,
            'correct_option_key' => $correctKey,
            'explanation_en' => "\"{$wordsEn[$oddIndex]}\" is the odd one out - the others share a common category.",
            'explanation_si' => "\"{$wordsEn[$oddIndex]}\" is the odd one out - the others share a common category.",
            'difficulty_weight' => $difficulty,
            'solving_time_seconds' => $this->estimatedTimeFor($difficulty),
        ];
    }

    /**
     * Deterministic lookup (not a guess): a fixed baseline plus a per-
     * difficulty-step increment, matching the same order-of-magnitude
     * (level 1 ~30s, harder items ~120s) as the brief's own worked example -
     * see Question::expectedTimeSeconds()/ResponseTimeCalibrationService for
     * how this authored baseline later gets replaced by a learned value.
     */
    private function estimatedTimeFor(int $difficulty): int
    {
        return 25 + ($difficulty - 1) * 15;
    }

    private function memory(int $difficulty): array
    {
        $length = 3 + $difficulty;
        $digits = [];
        for ($i = 0; $i < $length; $i++) {
            $digits[] = random_int(0, 9);
        }
        $position = random_int(1, $length);
        $answer = (string) $digits[$position - 1];
        $sequence = implode('-', $digits);

        $distractors = collect(range(0, 9))->reject(fn ($d) => (string) $d === $answer)->shuffle()->take(3)->map(fn ($d) => (string) $d)->all();

        return $this->buildMcq(
            questionEn: "Memorize this sequence: {$sequence}. What was the {$this->ordinal($position)} number?",
            questionSi: "Memorize this sequence: {$sequence}. Number {$position}?",
            correctAnswer: $answer,
            distractors: $distractors,
            explanationEn: "The {$this->ordinal($position)} number in {$sequence} is {$answer}.",
            explanationSi: "Number {$position} in {$sequence} is {$answer}.",
            difficulty: $difficulty,
        );
    }

    private function attention(int $difficulty): array
    {
        $letters = ['A', 'B', 'C', 'D', 'E'];
        $target = $letters[array_rand($letters)];
        $length = 12 + $difficulty * 4;
        $string = '';
        $count = 0;
        for ($i = 0; $i < $length; $i++) {
            $letter = $letters[array_rand($letters)];
            if ($letter === $target) {
                $count++;
            }
            $string .= $letter;
        }
        $answer = (string) $count;
        $distractors = collect([$count + 1, max(0, $count - 1), $count + 2])->unique()->map(fn ($n) => (string) $n)->take(3)->all();
        while (count($distractors) < 3) {
            $distractors[] = (string) ($count + count($distractors) + 3);
        }

        return $this->buildMcq(
            questionEn: "How many times does the letter '{$target}' appear in: {$string}?",
            questionSi: "How many times does '{$target}' appear in: {$string}?",
            correctAnswer: $answer,
            distractors: $distractors,
            explanationEn: "The letter '{$target}' appears {$count} time(s) in the string.",
            explanationSi: "'{$target}' appears {$count} time(s).",
            difficulty: $difficulty,
        );
    }

    private function spatialPattern(int $difficulty): array
    {
        $ratio = $difficulty >= 3 ? 3 : 2;
        $start = random_int(1, 3);
        $sequence = [$start, $start * $ratio, $start * $ratio ** 2, $start * $ratio ** 3];
        $answer = $start * $ratio ** 4;
        $distractors = [$answer + $ratio, max(1, $answer - $ratio), $answer * 2];
        $sequenceStr = implode(', ', $sequence);

        return $this->buildMcq(
            questionEn: "What comes next in the pattern: {$sequenceStr}, ?",
            questionSi: "What comes next: {$sequenceStr}, ?",
            correctAnswer: (string) $answer,
            distractors: array_map('strval', $distractors),
            explanationEn: "Each number is multiplied by {$ratio} to get the next one.",
            explanationSi: "Each number ×{$ratio} gives the next one.",
            difficulty: $difficulty,
        );
    }

    private function buildMcq(
        string $questionEn,
        string $questionSi,
        string $correctAnswer,
        array $distractors,
        string $explanationEn,
        string $explanationSi,
        int $difficulty
    ): array {
        $allAnswers = collect([$correctAnswer, ...array_slice($distractors, 0, 3)])->unique()->values();
        while ($allAnswers->count() < 4) {
            $allAnswers->push((string) (((int) $correctAnswer) + $allAnswers->count() + 10));
        }
        $shuffled = $allAnswers->shuffle()->values();
        $keys = ['A', 'B', 'C', 'D'];
        $options = [];
        $correctKey = 'A';
        foreach ($shuffled as $i => $value) {
            $key = $keys[$i];
            $options[] = ['key' => $key, 'text_en' => $value, 'text_si' => $value];
            if ($value === $correctAnswer) {
                $correctKey = $key;
            }
        }

        return [
            'question_text_en' => $questionEn,
            'question_text_si' => $questionSi,
            'options' => $options,
            'correct_option_key' => $correctKey,
            'explanation_en' => $explanationEn,
            'explanation_si' => $explanationSi,
            'difficulty_weight' => $difficulty,
            'solving_time_seconds' => $this->estimatedTimeFor($difficulty),
        ];
    }

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$n}{$suffix}";
    }
}

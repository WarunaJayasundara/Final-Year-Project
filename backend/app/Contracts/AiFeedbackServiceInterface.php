<?php

namespace App\Contracts;

use App\Models\Question;

interface AiFeedbackServiceInterface
{
    /**
     * Produce a beginner-friendly explanation of why the student's selected
     * option was wrong (or right) for the given question, in the requested locale.
     */
    public function explainAnswer(Question $question, string $selectedOptionKey, string $locale): string;
}

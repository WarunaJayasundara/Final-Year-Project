<?php

namespace App\Contracts;

use App\Models\Category;
use App\Models\IqLevel;

interface AiQuestionGeneratorServiceInterface
{
    /**
     * Generate one candidate MCQ question for the given category/level, in
     * both languages. Returns a plain array matching the shape expected by
     * QuestionDraftService (not yet persisted, not yet validated for
     * duplicates - the caller handles both).
     *
     * @param string|null $examCategoryLabel Optional government-exam context
     *   (e.g. "Sri Lanka Administrative Service (SLAS)") to bias question style.
     * @param string[] $avoidQuestionTexts A sample of existing question texts
     *   in this category, included in the prompt so the model steers away
     *   from near-duplicates up front (the actual similarity check still
     *   happens after generation - this is a best-effort prompt hint, not a
     *   guarantee).
     */
    public function generate(
        Category $category,
        IqLevel $level,
        ?string $examCategoryLabel,
        array $avoidQuestionTexts
    ): array;
}

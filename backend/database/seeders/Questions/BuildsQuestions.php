<?php

namespace Database\Seeders\Questions;

use App\Models\Category;
use App\Models\IqLevel;
use Illuminate\Support\Carbon;

/**
 * Shared helpers for the per-category question seeders: resolves the
 * category/level ids once, and inserts a batch of question rows with
 * sensible defaults so each seeder file only has to describe content.
 */
trait BuildsQuestions
{
    private function categoryId(string $code): int
    {
        return Category::where('code', $code)->value('id');
    }

    /** @return array<int,int> level_number => id */
    private function levelIds(): array
    {
        return IqLevel::pluck('id', 'level_number')->all();
    }

    /**
     * @param  array<int,array>  $rows  each row: [level_number, question_type, text_en, text_si, options, correct_key, explanation_en, explanation_si, difficulty_weight?, meta?]
     *                                  meta (index 9) is an optional assoc array: subcategory, solving_time_seconds,
     *                                  bloom_level, exam_tags (array), cognitive_skill, image_path,
     *                                  source_type, source_document_reference (informal string, not a source_documents FK -
     *                                  seeder-authored content predates the admin ingestion feature and isn't tied to a
     *                                  specific uploaded row), difficulty_reason, generation_rule (image questions only -
     *                                  the SvgFigureBuilder transformation name, e.g. "rotate_and_recolor"),
     *                                  transformation_steps (array, image questions only), visual_complexity_score (float).
     * @param  array  $defaultMeta  batch-level metadata defaults, overridden by per-row meta
     */
    private function insertRows(string $categoryCode, array $rows, array $defaultMeta = []): void
    {
        $categoryId = $this->categoryId($categoryCode);
        $levelIds = $this->levelIds();
        $now = Carbon::now();

        $payload = array_map(function (array $row) use ($categoryId, $levelIds, $now, $defaultMeta) {
            [$levelNumber, $type, $textEn, $textSi, $options, $correctKey, $explanationEn, $explanationSi] = $row;
            // Difficulty weight tracks the IQ level directly (1-5). Used to
            // cap out at 3 distinct values, so Level 5 wasn't reliably
            // harder than Level 3 - fixed to scale with the level itself.
            $difficulty = $row[8] ?? max(1, min(5, (int) $levelNumber));
            $meta = array_merge($defaultMeta, $row[9] ?? []);

            return [
                'category_id' => $categoryId,
                'level_id' => $levelIds[$levelNumber],
                'question_type' => $type,
                'subcategory' => $meta['subcategory'] ?? null,
                'question_text_en' => $textEn,
                'question_text_si' => $textSi,
                'image_path' => $meta['image_path'] ?? null,
                'generation_rule' => $meta['generation_rule'] ?? null,
                'transformation_steps' => isset($meta['transformation_steps']) ? json_encode($meta['transformation_steps']) : null,
                'visual_complexity_score' => $meta['visual_complexity_score'] ?? null,
                'options' => json_encode($options),
                'correct_option_key' => $correctKey,
                'explanation_en' => $explanationEn,
                'explanation_si' => $explanationSi,
                'difficulty_weight' => $difficulty,
                'solving_time_seconds' => $meta['solving_time_seconds'] ?? null,
                'bloom_level' => $meta['bloom_level'] ?? null,
                'exam_tags' => isset($meta['exam_tags']) ? json_encode($meta['exam_tags']) : null,
                'cognitive_skill' => $meta['cognitive_skill'] ?? null,
                'is_active' => true,
                'created_by' => null,
                // Seeder rows are generated with a computed answer, so
                // 'auto_validated' is accurate here; 'human_approved' is
                // reserved for rows that went through admin review.
                'source_type' => $meta['source_type'] ?? 'original',
                'generation_method' => 'seeder',
                'validation_status' => $meta['validation_status'] ?? 'auto_validated',
                'difficulty_reason' => $meta['difficulty_reason'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows);

        foreach (array_chunk($payload, 100) as $chunk) {
            \App\Models\Question::insert($chunk);
        }
    }

    /** Builds an options array of {key,text_en,text_si} from parallel EN/SI label lists. */
    private function options(array $labelsEn, array $labelsSi): array
    {
        $keys = ['A', 'B', 'C', 'D', 'E', 'F'];
        $out = [];
        foreach (array_values($labelsEn) as $i => $en) {
            $out[] = ['key' => $keys[$i], 'text_en' => $en, 'text_si' => $labelsSi[$i]];
        }
        return $out;
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'level_id' => ['required', 'exists:iq_levels,id'],
            'question_type' => ['required', 'in:mcq_text,mcq_image'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'question_text_en' => ['required', 'string'],
            'question_text_si' => ['required', 'string'],
            'image_path' => ['nullable', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.key' => ['required', 'string', 'max:1'],
            'options.*.text_en' => ['required', 'string'],
            'options.*.text_si' => ['required', 'string'],
            'correct_option_key' => ['required', 'string', 'max:1'],
            'explanation_en' => ['nullable', 'string'],
            'explanation_si' => ['nullable', 'string'],
            // Tracks the IQ level directly (1-5, matching the 5 seeded iq_levels
            // rows) since the difficulty-weight formula fix - see BuildsQuestions.
            'difficulty_weight' => ['nullable', 'integer', 'min:1', 'max:5'],
            'exam_tags' => ['nullable', 'array'],
            'exam_tags.*' => ['string'],
            'solving_time_seconds' => ['nullable', 'integer', 'min:1'],
            'bloom_level' => ['nullable', 'string', 'max:50'],
            'cognitive_skill' => ['nullable', 'string', 'max:100'],
            'generation_rule' => ['nullable', 'string', 'max:100'],
            'transformation_steps' => ['nullable', 'array'],
            'visual_complexity_score' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'level_id' => ['sometimes', 'required', 'exists:iq_levels,id'],
            'question_type' => ['sometimes', 'required', 'in:mcq_text,mcq_image'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'question_text_en' => ['sometimes', 'required', 'string'],
            'question_text_si' => ['sometimes', 'required', 'string'],
            'image_path' => ['nullable', 'string'],
            'options' => ['sometimes', 'required', 'array', 'min:2'],
            'options.*.key' => ['required_with:options', 'string', 'max:1'],
            'options.*.text_en' => ['required_with:options', 'string'],
            'options.*.text_si' => ['required_with:options', 'string'],
            'correct_option_key' => ['sometimes', 'required', 'string', 'max:1'],
            'explanation_en' => ['nullable', 'string'],
            'explanation_si' => ['nullable', 'string'],
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

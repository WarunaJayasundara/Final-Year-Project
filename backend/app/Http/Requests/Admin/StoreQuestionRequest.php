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
            'question_text_en' => ['required', 'string'],
            'question_text_si' => ['required', 'string'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.key' => ['required', 'string', 'max:1'],
            'options.*.text_en' => ['required', 'string'],
            'options.*.text_si' => ['required', 'string'],
            'correct_option_key' => ['required', 'string', 'max:1'],
            'explanation_en' => ['nullable', 'string'],
            'explanation_si' => ['nullable', 'string'],
            'difficulty_weight' => ['nullable', 'integer', 'min:1', 'max:3'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

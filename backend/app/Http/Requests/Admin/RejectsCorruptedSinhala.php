<?php

namespace App\Http\Requests\Admin;

use App\Services\QuestionBank\SinhalaTextGuard;
use Illuminate\Validation\Validator;

/**
 * Blocks saving a question whose Sinhala is corrupted (stray scripts, orphaned
 * vowel signs, no Sinhala letters at all). Only clear corruption is rejected
 * here; softer concerns are surfaced as warnings in the admin form.
 */
trait RejectsCorruptedSinhala
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $guard = new SinhalaTextGuard();

            $fields = [
                'question_text_si' => [$this->input('question_text_si'), true],
                'explanation_si' => [$this->input('explanation_si'), true],
            ];
            foreach ((array) $this->input('options', []) as $i => $option) {
                $fields["options.{$i}.text_si"] = [$option['text_si'] ?? null, false];
            }

            foreach ($fields as $name => [$value, $isSentence]) {
                if (! is_string($value) || trim($value) === '') {
                    continue;
                }
                foreach ($guard->inspect($value, null, $isSentence) as $issue) {
                    if ($issue['severity'] === 'error') {
                        $v->errors()->add($name, $issue['message']);
                        break;
                    }
                }
            }
        });
    }
}

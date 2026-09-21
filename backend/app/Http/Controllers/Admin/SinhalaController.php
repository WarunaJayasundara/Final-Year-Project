<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionBank\SinhalaTextGuard;
use App\Services\QuestionBank\SinhalaTranslationService;
use App\Services\QuestionBank\SinhalaTranslationUnavailable;
use Illuminate\Http\Request;

class SinhalaController extends Controller
{
    /** Machine-draft Sinhala for English fields. Never saved here; the admin reviews it in the form. */
    public function translate(Request $request, SinhalaTranslationService $service)
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1', 'max:14'],
            'fields.*' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            return response()->json(['data' => $service->translate($data['fields'])]);
        } catch (SinhalaTranslationUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** Integrity check of Sinhala text the admin typed or pasted. */
    public function check(Request $request, SinhalaTextGuard $guard)
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1', 'max:20'],
            'fields.*.si' => ['nullable', 'string', 'max:4000'],
            'fields.*.en' => ['nullable', 'string', 'max:4000'],
            'fields.*.is_option' => ['nullable', 'boolean'],
        ]);

        $issues = [];
        foreach ($data['fields'] as $key => $field) {
            $found = $guard->inspect($field['si'] ?? '', $field['en'] ?? null, ! ($field['is_option'] ?? false));
            if ($found !== []) {
                $issues[$key] = $found;
            }
        }

        return response()->json(['data' => ['issues' => $issues]]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Category::orderBy('name_en')->get()]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'alpha_dash', 'unique:categories,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_si' => ['required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_si' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $category = Category::create($validator->validated());

        return response()->json(['data' => $category], 201);
    }

    public function show(Category $category)
    {
        return response()->json(['data' => $category]);
    }

    public function update(Request $request, Category $category)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'required', 'string', 'alpha_dash', 'unique:categories,code,'.$category->id],
            'name_en' => ['sometimes', 'required', 'string', 'max:255'],
            'name_si' => ['sometimes', 'required', 'string', 'max:255'],
            'description_en' => ['nullable', 'string'],
            'description_si' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $category->update($validator->validated());

        return response()->json(['data' => $category->fresh()]);
    }

    public function destroy(Category $category)
    {
        if ($category->questions()->exists()) {
            return response()->json(['message' => 'Cannot delete a category that still has questions.'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}

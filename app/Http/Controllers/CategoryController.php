<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\User;
use Illuminate\Validation\Rule;


class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->select(['id', 'name', 'slug',])
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN],true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],

            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', 'exists:subjects,id']
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        if (!empty($data['subjects'])) {
            $category->subjects()->sync($data['subjects']);
        }

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category->load('subjects'),
        ], 201);
    }

    public function show(Category $category)
    {
        $category->load([
            'subjects:id,name,description'
        ]);

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN], true),
            403, 'Access denied'
        );

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'description' => ['nullable', 'string'],

            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', 'exists:subjects,id'],
        ]);

        $category->update([
            'name' => $data['name'] ?? $category->name,
            'slug' => $data['slug'] ?? $category->slug,
            'description' => $data['description'] ?? $category->description,
        ]);

        if (array_key_exists('subjects', $data)) {
            $category->subjects()->sync($data['subjects']);
        }

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->load('subjects')
        ]);
    }

    public function destroy(Request $request, Category $category)
    {
        $user = $request->user();

        abort_unless(in_array($user->role, [User::ROLE_ADMIN,],true),
            403, 'Access denied'
        );

        abort_if($category->projects()->exists(),
            422, 'Cannot delete category that has projects'
        );

        // удаляем связи с предметами
        $category->subjects()->detach();

        // удаляем категорию
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}

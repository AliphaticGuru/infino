<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Data\UpdateCategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\UniqueSlug;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateCategory implements UpdatesCategories
{
    public function handle(Category $category, UpdateCategoryData $data): Category
    {
        $validated = Validator::make(
            [
                'name' => $data->name,
                'description' => $data->description,
                'parent_id' => $data->parentId,
                'is_active' => $data->isActive,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
                'is_active' => ['nullable', 'boolean'],
            ],
        )->validate();

        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = Category::query()->whereKey($parentId)->firstOrFail();

            if ($parent->is($category) || $parent->isDescendantOf($category)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A category cannot be its own parent or a descendant of itself.',
                ]);
            }
        }

        $slug = $category->name === $validated['name']
            ? $category->slug
            : UniqueSlug::generate(Category::class, $validated['name'], 'category', $category->id);

        $attributes = [
            'name' => $validated['name'],
            'slug' => $slug,
        ];

        if ($data->description !== null) {
            $attributes['description'] = $validated['description'];
        }

        if ($parentId !== null) {
            $attributes['parent_id'] = $parentId;
        }

        if ($data->isActive !== null) {
            $attributes['is_active'] = $validated['is_active'];
        }

        $category->fill($attributes)->save();

        return $category->refresh();
    }
}

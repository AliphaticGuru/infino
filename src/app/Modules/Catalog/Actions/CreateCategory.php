<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Data\CreateCategoryData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\UniqueSlug;
use Illuminate\Support\Facades\Validator;

final class CreateCategory implements CreatesCategories
{
    public function handle(CreateCategoryData $data): Category
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
                'is_active' => ['boolean'],
            ],
        )->validate();

        return Category::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => UniqueSlug::generate(Category::class, $validated['name'], 'category'),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }
}

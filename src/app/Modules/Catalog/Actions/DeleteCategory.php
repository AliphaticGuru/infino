<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteCategory implements DeletesCategories
{
    public function handle(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $current = Category::query()
                ->whereKey($category->getKey())
                ->firstOrFail();

            if ($current->products()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'A category with assigned products cannot be deleted.',
                ]);
            }

            $current->delete();
        });
    }
}

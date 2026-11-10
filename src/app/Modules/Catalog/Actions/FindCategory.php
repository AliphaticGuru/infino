<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\FindsCategories;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class FindCategory implements FindsCategories
{
    public function find(int $id): Category
    {
        return Category::query()->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginate(int $perPage = 20, ?int $page = null): LengthAwarePaginator
    {
        return Category::query()
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(Category $category, int $perPage = 20, ?int $page = null): LengthAwarePaginator
    {
        $current = Category::query()
            ->whereKey($category->getKey())
            ->firstOrFail();

        return Product::query()
            ->whereHas(
                'categories',
                static fn (Builder $query) => $query->whereKey($current->getKey()),
            )
            ->orderBy('products.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}

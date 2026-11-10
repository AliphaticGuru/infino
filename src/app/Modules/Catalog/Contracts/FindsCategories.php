<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

interface FindsCategories
{
    public function find(int $id): Category;

    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginate(int $perPage = 20, ?int $page = null): LengthAwarePaginator;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function products(Category $category, int $perPage = 20, ?int $page = null): LengthAwarePaginator;
}

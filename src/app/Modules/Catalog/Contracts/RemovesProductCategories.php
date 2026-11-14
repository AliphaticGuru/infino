<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;

interface RemovesProductCategories
{
    /**
     * @param  array<int, int>  $categoryIds
     */
    public function handle(Product $product, array $categoryIds): Product;
}

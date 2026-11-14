<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;

interface RemovesProductAttributes
{
    /**
     * @param  array<int, int>  $attributeIds
     */
    public function handle(Product $product, array $attributeIds): Product;
}

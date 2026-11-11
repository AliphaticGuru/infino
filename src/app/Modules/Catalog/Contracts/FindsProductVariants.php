<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

interface FindsProductVariants
{
    /**
     * @return Collection<int, ProductVariant>
     */
    public function forProduct(Product $product): Collection;

    public function find(int $id): ProductVariant;

    public function findForProduct(Product $product, int $id): ProductVariant;
}

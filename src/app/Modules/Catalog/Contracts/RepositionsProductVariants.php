<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

interface RepositionsProductVariants
{
    /**
     * @param  array<int, int>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    public function handle(Product $product, array $variantIds): Collection;
}

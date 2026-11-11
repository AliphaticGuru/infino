<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\FindsProductVariants;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

final class FindProductVariant implements FindsProductVariants
{
    /**
     * @return Collection<int, ProductVariant>
     */
    public function forProduct(Product $product): Collection
    {
        $current = Product::query()
            ->whereKey($product->getKey())
            ->firstOrFail();

        return $current->variants()
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ProductVariant
    {
        return ProductVariant::query()->findOrFail($id);
    }

    public function findForProduct(Product $product, int $id): ProductVariant
    {
        return ProductVariant::query()
            ->whereKey($id)
            ->where('product_id', $product->getKey())
            ->firstOrFail();
    }
}

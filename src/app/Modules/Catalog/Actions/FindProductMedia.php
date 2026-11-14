<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\FindsProductMedia;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

final class FindProductMedia implements FindsProductMedia
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public function forProduct(Product $product): Collection
    {
        $current = Product::query()
            ->whereKey($product->getKey())
            ->firstOrFail();

        return $current->media()->get();
    }

    /**
     * @return Collection<int, ProductMedia>
     */
    public function forVariant(ProductVariant $variant): Collection
    {
        $current = ProductVariant::query()
            ->whereKey($variant->getKey())
            ->firstOrFail();

        return $current->media()->get();
    }

    public function find(int $id): ProductMedia
    {
        return ProductMedia::query()->findOrFail($id);
    }
}

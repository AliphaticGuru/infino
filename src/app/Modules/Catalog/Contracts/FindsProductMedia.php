<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface FindsProductMedia
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public function forProduct(Product $product): Collection;

    /**
     * @return Collection<int, ProductMedia>
     */
    public function forVariant(ProductVariant $variant): Collection;

    /**
     * @throws ModelNotFoundException
     */
    public function find(int $id): ProductMedia;
}

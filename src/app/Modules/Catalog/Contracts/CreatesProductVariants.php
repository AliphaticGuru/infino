<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\CreateProductVariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

interface CreatesProductVariants
{
    public function handle(Product $product, CreateProductVariantData $data): ProductVariant;
}

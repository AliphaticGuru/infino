<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\UpdateProductVariantData;
use App\Modules\Catalog\Models\ProductVariant;

interface UpdatesProductVariants
{
    public function handle(ProductVariant $variant, UpdateProductVariantData $data): ProductVariant;
}

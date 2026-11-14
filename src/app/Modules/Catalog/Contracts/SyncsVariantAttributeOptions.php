<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\SyncVariantAttributeOptionsData;
use App\Modules\Catalog\Models\ProductVariant;

interface SyncsVariantAttributeOptions
{
    public function handle(ProductVariant $variant, SyncVariantAttributeOptionsData $data): ProductVariant;
}

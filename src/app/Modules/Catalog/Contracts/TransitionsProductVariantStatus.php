<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductVariant;

interface TransitionsProductVariantStatus
{
    public function activate(ProductVariant $variant): ProductVariant;

    public function deactivate(ProductVariant $variant): ProductVariant;
}

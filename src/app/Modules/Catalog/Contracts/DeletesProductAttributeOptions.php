<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductAttributeOption;

interface DeletesProductAttributeOptions
{
    public function handle(ProductAttributeOption $option): void;
}

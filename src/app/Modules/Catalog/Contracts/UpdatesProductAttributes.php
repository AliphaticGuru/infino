<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\UpdateProductAttributeData;
use App\Modules\Catalog\Models\ProductAttribute;

interface UpdatesProductAttributes
{
    public function handle(ProductAttribute $attribute, UpdateProductAttributeData $data): ProductAttribute;
}

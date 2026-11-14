<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\CreateProductAttributeOptionData;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;

interface CreatesProductAttributeOptions
{
    public function handle(ProductAttribute $attribute, CreateProductAttributeOptionData $data): ProductAttributeOption;
}

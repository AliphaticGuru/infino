<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\UpdateProductAttributeOptionData;
use App\Modules\Catalog\Models\ProductAttributeOption;

interface UpdatesProductAttributeOptions
{
    public function handle(ProductAttributeOption $option, UpdateProductAttributeOptionData $data): ProductAttributeOption;
}

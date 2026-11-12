<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\CreateProductAttributeData;
use App\Modules\Catalog\Models\ProductAttribute;

interface CreatesProductAttributes
{
    public function handle(CreateProductAttributeData $data): ProductAttribute;
}

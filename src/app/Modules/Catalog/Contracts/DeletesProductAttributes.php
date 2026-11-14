<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductAttribute;

interface DeletesProductAttributes
{
    public function handle(ProductAttribute $attribute): void;
}

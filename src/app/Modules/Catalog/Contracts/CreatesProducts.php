<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Models\Product;

interface CreatesProducts
{
    public function handle(CreateProductData $data): Product;
}

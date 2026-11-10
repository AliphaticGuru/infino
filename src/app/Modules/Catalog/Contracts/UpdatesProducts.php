<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\UpdateProductData;
use App\Modules\Catalog\Models\Product;

interface UpdatesProducts
{
    public function handle(Product $product, UpdateProductData $data): Product;
}

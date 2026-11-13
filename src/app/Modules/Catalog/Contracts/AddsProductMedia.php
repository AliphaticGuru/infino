<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\AddProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;

interface AddsProductMedia
{
    public function handle(Product $product, AddProductMediaData $data): ProductMedia;
}

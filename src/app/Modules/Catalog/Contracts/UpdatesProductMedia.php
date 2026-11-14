<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\UpdateProductMediaData;
use App\Modules\Catalog\Models\ProductMedia;

interface UpdatesProductMedia
{
    public function handle(ProductMedia $media, UpdateProductMediaData $data): ProductMedia;
}

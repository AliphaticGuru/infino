<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductMedia;

interface SetsPrimaryProductMedia
{
    public function handle(ProductMedia $media): ProductMedia;
}

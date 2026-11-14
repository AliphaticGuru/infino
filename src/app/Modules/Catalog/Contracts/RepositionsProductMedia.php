<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\RepositionProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use Illuminate\Database\Eloquent\Collection;

interface RepositionsProductMedia
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public function handle(Product $product, RepositionProductMediaData $data): Collection;
}

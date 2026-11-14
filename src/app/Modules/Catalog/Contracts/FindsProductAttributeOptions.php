<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Pagination\LengthAwarePaginator;

interface FindsProductAttributeOptions
{
    public function find(int $id): ProductAttributeOption;

    /**
     * @return LengthAwarePaginator<int, ProductAttributeOption>
     */
    public function forAttribute(ProductAttribute $attribute, int $perPage = 20, ?int $page = null): LengthAwarePaginator;
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductAttribute;
use Illuminate\Pagination\LengthAwarePaginator;

interface FindsProductAttributes
{
    public function find(int $id): ProductAttribute;

    /**
     * @return LengthAwarePaginator<int, ProductAttribute>
     */
    public function paginate(int $perPage = 20, ?int $page = null): LengthAwarePaginator;
}

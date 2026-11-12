<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\FindsProductAttributes;
use App\Modules\Catalog\Models\ProductAttribute;
use Illuminate\Pagination\LengthAwarePaginator;

final class FindProductAttribute implements FindsProductAttributes
{
    public function find(int $id): ProductAttribute
    {
        return ProductAttribute::query()->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator<int, ProductAttribute>
     */
    public function paginate(int $perPage = 20, ?int $page = null): LengthAwarePaginator
    {
        return ProductAttribute::query()
            ->orderBy('position')
            ->orderBy('code')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}

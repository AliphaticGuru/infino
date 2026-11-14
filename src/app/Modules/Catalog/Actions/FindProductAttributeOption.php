<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\FindsProductAttributeOptions;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Pagination\LengthAwarePaginator;

final class FindProductAttributeOption implements FindsProductAttributeOptions
{
    public function find(int $id): ProductAttributeOption
    {
        return ProductAttributeOption::query()->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator<int, ProductAttributeOption>
     */
    public function forAttribute(ProductAttribute $attribute, int $perPage = 20, ?int $page = null): LengthAwarePaginator
    {
        return ProductAttributeOption::query()
            ->where('product_attribute_id', $attribute->getKey())
            ->orderBy('position')
            ->orderBy('code')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}

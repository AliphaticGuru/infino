<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Database\Eloquent\Collection;

interface RepositionsProductAttributeOptions
{
    /**
     * @param  array<int, int>  $optionIds
     * @return Collection<int, ProductAttributeOption>
     */
    public function handle(ProductAttribute $attribute, array $optionIds): Collection;
}

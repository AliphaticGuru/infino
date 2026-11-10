<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Models\Product;

final class FindProduct implements FindsProducts
{
    public function find(int $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    public function findBySlug(string $slug): Product
    {
        return Product::query()->where('slug', $slug)->firstOrFail();
    }
}

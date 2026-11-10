<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;

interface FindsProducts
{
    public function find(int $id): Product;

    public function findBySlug(string $slug): Product;
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Product;

interface TransitionsProductStatus
{
    public function publish(Product $product): Product;

    public function unpublish(Product $product): Product;

    public function archive(Product $product): Product;

    public function restore(Product $product): Product;
}

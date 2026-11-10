<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Models\Category;

interface DeletesCategories
{
    public function handle(Category $category): void;
}

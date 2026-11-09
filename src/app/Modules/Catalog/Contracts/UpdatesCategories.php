<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\UpdateCategoryData;
use App\Modules\Catalog\Models\Category;

interface UpdatesCategories
{
    public function handle(Category $category, UpdateCategoryData $data): Category;
}

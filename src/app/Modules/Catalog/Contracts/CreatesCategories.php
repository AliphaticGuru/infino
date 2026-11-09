<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Data\CreateCategoryData;
use App\Modules\Catalog\Models\Category;

interface CreatesCategories
{
    public function handle(CreateCategoryData $data): Category;
}

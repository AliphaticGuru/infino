<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Contracts\UpdatesCategories;

final class CatalogServiceProvider extends BaseModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->bind(
            CreatesProducts::class,
            CreateProduct::class,
        );

        $this->app->bind(
            CreatesCategories::class,
            CreateCategory::class,
        );

        $this->app->bind(
            UpdatesCategories::class,
            UpdateCategory::class,
        );
    }
}
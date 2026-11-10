<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\FindProduct;
use App\Modules\Catalog\Actions\TransitionProductStatus;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Core\Providers\BaseModuleServiceProvider;

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

        $this->app->bind(
            FindsProducts::class,
            FindProduct::class,
        );

        $this->app->bind(
            UpdatesProducts::class,
            UpdateProduct::class,
        );

        $this->app->bind(
            TransitionsProductStatus::class,
            TransitionProductStatus::class,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
<<<<<<< develop
=======
use App\Modules\Catalog\Actions\FindProduct;
use App\Modules\Catalog\Actions\TransitionProductStatus;
>>>>>>> local
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\CreatesProducts;
<<<<<<< develop
=======
use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
>>>>>>> local
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
<<<<<<< develop
=======

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
>>>>>>> local
    }
}
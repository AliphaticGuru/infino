<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Actions\AssignProductCategories;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\FindCategory;
use App\Modules\Catalog\Actions\FindProduct;
use App\Modules\Catalog\Actions\RemoveProductCategories;
use App\Modules\Catalog\Actions\SyncProductCategories;
use App\Modules\Catalog\Actions\TransitionProductStatus;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Contracts\AssignsProductCategories;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Contracts\FindsCategories;
use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Contracts\RemovesProductCategories;
use App\Modules\Catalog\Contracts\SyncsProductCategories;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Policies\CategoryPolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Core\Providers\BaseModuleServiceProvider;
use Illuminate\Support\Facades\Gate;

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

        $this->app->bind(
            AssignsProductCategories::class,
            AssignProductCategories::class,
        );

        $this->app->bind(
            RemovesProductCategories::class,
            RemoveProductCategories::class,
        );

        $this->app->bind(
            SyncsProductCategories::class,
            SyncProductCategories::class,
        );

        $this->app->bind(
            FindsCategories::class,
            FindCategory::class,
        );

        $this->app->bind(
            DeletesCategories::class,
            DeleteCategory::class,
        );
    }

    protected function bootModule(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}

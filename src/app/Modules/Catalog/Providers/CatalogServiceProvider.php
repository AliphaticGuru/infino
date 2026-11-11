<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Actions\AssignProductCategories;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\FindCategory;
use App\Modules\Catalog\Actions\FindProduct;
use App\Modules\Catalog\Actions\FindProductVariant;
use App\Modules\Catalog\Actions\RemoveProductCategories;
use App\Modules\Catalog\Actions\RepositionProductVariants;
use App\Modules\Catalog\Actions\SyncProductCategories;
use App\Modules\Catalog\Actions\TransitionProductStatus;
use App\Modules\Catalog\Actions\TransitionProductVariantStatus;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Actions\UpdateProductVariant;
use App\Modules\Catalog\Contracts\AssignsProductCategories;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Contracts\FindsCategories;
use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Contracts\FindsProductVariants;
use App\Modules\Catalog\Contracts\RemovesProductCategories;
use App\Modules\Catalog\Contracts\RepositionsProductVariants;
use App\Modules\Catalog\Contracts\SyncsProductCategories;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Contracts\TransitionsProductVariantStatus;
use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Contracts\UpdatesProductVariants;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Policies\CategoryPolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Catalog\Policies\ProductVariantPolicy;
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

        $this->app->bind(
            FindsProductVariants::class,
            FindProductVariant::class,
        );

        $this->app->bind(
            CreatesProductVariants::class,
            CreateProductVariant::class,
        );

        $this->app->bind(
            UpdatesProductVariants::class,
            UpdateProductVariant::class,
        );

        $this->app->bind(
            TransitionsProductVariantStatus::class,
            TransitionProductVariantStatus::class,
        );

        $this->app->bind(
            RepositionsProductVariants::class,
            RepositionProductVariants::class,
        );
    }

    protected function bootModule(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
    }
}

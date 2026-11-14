<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Actions\AddProductMedia;
use App\Modules\Catalog\Actions\AssignProductAttributes;
use App\Modules\Catalog\Actions\AssignProductCategories;
use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\CreateProductAttribute;
use App\Modules\Catalog\Actions\CreateProductAttributeOption;
use App\Modules\Catalog\Actions\CreateProductVariant;
use App\Modules\Catalog\Actions\DeleteCategory;
use App\Modules\Catalog\Actions\DeleteProductAttribute;
use App\Modules\Catalog\Actions\DeleteProductAttributeOption;
use App\Modules\Catalog\Actions\DeleteProductMedia;
use App\Modules\Catalog\Actions\FindCategory;
use App\Modules\Catalog\Actions\FindProduct;
use App\Modules\Catalog\Actions\FindProductAttribute;
use App\Modules\Catalog\Actions\FindProductAttributeOption;
use App\Modules\Catalog\Actions\FindProductMedia;
use App\Modules\Catalog\Actions\FindProductVariant;
use App\Modules\Catalog\Actions\RemoveProductAttributes;
use App\Modules\Catalog\Actions\RemoveProductCategories;
use App\Modules\Catalog\Actions\RepositionProductAttributeOptions;
use App\Modules\Catalog\Actions\RepositionProductMedia;
use App\Modules\Catalog\Actions\RepositionProductVariants;
use App\Modules\Catalog\Actions\SetPrimaryProductMedia;
use App\Modules\Catalog\Actions\SyncProductAttributes;
use App\Modules\Catalog\Actions\SyncProductCategories;
use App\Modules\Catalog\Actions\SyncVariantAttributeOptions;
use App\Modules\Catalog\Actions\TransitionProductStatus;
use App\Modules\Catalog\Actions\TransitionProductVariantStatus;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Actions\UpdateProductAttribute;
use App\Modules\Catalog\Actions\UpdateProductAttributeOption;
use App\Modules\Catalog\Actions\UpdateProductMedia;
use App\Modules\Catalog\Actions\UpdateProductVariant;
use App\Modules\Catalog\Contracts\AddsProductMedia;
use App\Modules\Catalog\Contracts\AssignsProductAttributes;
use App\Modules\Catalog\Contracts\AssignsProductCategories;
use App\Modules\Catalog\Contracts\CreatesCategories;
use App\Modules\Catalog\Contracts\CreatesProductAttributeOptions;
use App\Modules\Catalog\Contracts\CreatesProductAttributes;
use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Contracts\DeletesCategories;
use App\Modules\Catalog\Contracts\DeletesProductAttributeOptions;
use App\Modules\Catalog\Contracts\DeletesProductAttributes;
use App\Modules\Catalog\Contracts\DeletesProductMedia;
use App\Modules\Catalog\Contracts\FindsCategories;
use App\Modules\Catalog\Contracts\FindsProductAttributeOptions;
use App\Modules\Catalog\Contracts\FindsProductAttributes;
use App\Modules\Catalog\Contracts\FindsProductMedia;
use App\Modules\Catalog\Contracts\FindsProducts;
use App\Modules\Catalog\Contracts\FindsProductVariants;
use App\Modules\Catalog\Contracts\MediaStorage;
use App\Modules\Catalog\Contracts\RemovesProductAttributes;
use App\Modules\Catalog\Contracts\RemovesProductCategories;
use App\Modules\Catalog\Contracts\RepositionsProductAttributeOptions;
use App\Modules\Catalog\Contracts\RepositionsProductMedia;
use App\Modules\Catalog\Contracts\RepositionsProductVariants;
use App\Modules\Catalog\Contracts\SetsPrimaryProductMedia;
use App\Modules\Catalog\Contracts\SyncsProductAttributes;
use App\Modules\Catalog\Contracts\SyncsProductCategories;
use App\Modules\Catalog\Contracts\SyncsVariantAttributeOptions;
use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Contracts\TransitionsProductVariantStatus;
use App\Modules\Catalog\Contracts\UpdatesCategories;
use App\Modules\Catalog\Contracts\UpdatesProductAttributeOptions;
use App\Modules\Catalog\Contracts\UpdatesProductAttributes;
use App\Modules\Catalog\Contracts\UpdatesProductMedia;
use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Contracts\UpdatesProductVariants;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Policies\CategoryPolicy;
use App\Modules\Catalog\Policies\ProductAttributeOptionPolicy;
use App\Modules\Catalog\Policies\ProductAttributePolicy;
use App\Modules\Catalog\Policies\ProductMediaPolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Catalog\Policies\ProductVariantPolicy;
use App\Modules\Catalog\Support\FilesystemMediaStorage;
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

        $this->app->bind(
            FindsProductAttributes::class,
            FindProductAttribute::class,
        );

        $this->app->bind(
            CreatesProductAttributes::class,
            CreateProductAttribute::class,
        );

        $this->app->bind(
            UpdatesProductAttributes::class,
            UpdateProductAttribute::class,
        );

        $this->app->bind(
            DeletesProductAttributes::class,
            DeleteProductAttribute::class,
        );

        $this->app->bind(
            FindsProductAttributeOptions::class,
            FindProductAttributeOption::class,
        );

        $this->app->bind(
            CreatesProductAttributeOptions::class,
            CreateProductAttributeOption::class,
        );

        $this->app->bind(
            UpdatesProductAttributeOptions::class,
            UpdateProductAttributeOption::class,
        );

        $this->app->bind(
            DeletesProductAttributeOptions::class,
            DeleteProductAttributeOption::class,
        );

        $this->app->bind(
            RepositionsProductAttributeOptions::class,
            RepositionProductAttributeOptions::class,
        );

        $this->app->bind(
            AssignsProductAttributes::class,
            AssignProductAttributes::class,
        );

        $this->app->bind(
            RemovesProductAttributes::class,
            RemoveProductAttributes::class,
        );

        $this->app->bind(
            SyncsProductAttributes::class,
            SyncProductAttributes::class,
        );

        $this->app->bind(
            SyncsVariantAttributeOptions::class,
            SyncVariantAttributeOptions::class,
        );

        $this->app->bind(
            FindsProductMedia::class,
            FindProductMedia::class,
        );

        $this->app->bind(
            AddsProductMedia::class,
            AddProductMedia::class,
        );

        $this->app->bind(
            UpdatesProductMedia::class,
            UpdateProductMedia::class,
        );

        $this->app->bind(
            SetsPrimaryProductMedia::class,
            SetPrimaryProductMedia::class,
        );

        $this->app->bind(
            DeletesProductMedia::class,
            DeleteProductMedia::class,
        );

        $this->app->bind(
            RepositionsProductMedia::class,
            RepositionProductMedia::class,
        );

        $this->app->bind(
            MediaStorage::class,
            FilesystemMediaStorage::class,
        );
    }

    protected function bootModule(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/catalog.php',
            'catalog',
        );

        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(ProductAttribute::class, ProductAttributePolicy::class);
        Gate::policy(ProductAttributeOption::class, ProductAttributeOptionPolicy::class);
        Gate::policy(ProductMedia::class, ProductMediaPolicy::class);
    }
}

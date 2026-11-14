<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Policies\VariantPricePolicy;
use App\Modules\Pricing\Actions\FindVariantPrices;
use App\Modules\Pricing\Actions\RemoveVariantPrice;
use App\Modules\Pricing\Actions\SetVariantPrice;
use App\Modules\Pricing\Contracts\FindsVariantPrices;
use App\Modules\Pricing\Contracts\RemovesVariantPrice;
use App\Modules\Pricing\Contracts\SetsVariantPrice;
use App\Modules\Pricing\Models\VariantPrice;
use Illuminate\Support\Facades\Gate;

final class PricingServiceProvider extends BaseModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->bind(
            FindsVariantPrices::class,
            FindVariantPrices::class,
        );

        $this->app->bind(
            SetsVariantPrice::class,
            SetVariantPrice::class,
        );

        $this->app->bind(
            RemovesVariantPrice::class,
            RemoveVariantPrice::class,
        );

        $this->app->bind(
            VariantPricePolicy::class,
            VariantPricePolicy::class,
        );
    }

    protected function bootModule(): void
    {
        $this->registerPricingConfig();

        Gate::policy(
            VariantPrice::class,
            VariantPricePolicy::class,
        );
    }

    protected function registerPricingConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/pricing.php',
            'pricing',
        );
    }
}

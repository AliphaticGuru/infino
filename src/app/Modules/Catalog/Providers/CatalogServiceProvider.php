<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Contracts\CreatesProducts;


final class CatalogServiceProvider extends BaseModuleServiceProvider
{   
    protected function registerModule(): void
    {
        $this->app->bind(
            CreatesProducts::class,
            CreateProduct::class,
        );
    }
}
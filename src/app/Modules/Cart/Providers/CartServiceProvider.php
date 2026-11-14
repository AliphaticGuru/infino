<?php

declare(strict_types=1);

namespace App\Modules\Cart\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;

final class CartServiceProvider extends BaseModuleServiceProvider
{
    protected function registerModule(): void
    {
        // Cart-specific bindings can go here
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Providers;

use App\Modules\Core\Providers\BaseModuleServiceProvider;
use App\Modules\Inventory\Actions\AdjustStock;
use App\Modules\Inventory\Actions\CreateInventory;
use App\Modules\Inventory\Contracts\AdjustsStock;
use App\Modules\Inventory\Contracts\CreatesInventory;

final class InventoryServiceProvider extends BaseModuleServiceProvider
{
    protected function registerModule(): void
    {
        $this->app->bind(
            CreatesInventory::class,
            CreateInventory::class,
        );

        $this->app->bind(
            AdjustsStock::class,
            AdjustStock::class,
        );
    }
}

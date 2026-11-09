<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Data\AdjustStockData;
use App\Modules\Inventory\Models\Inventory;

interface AdjustsStock
{
    public function handle(Inventory $inventory, AdjustStockData $data): Inventory;
}

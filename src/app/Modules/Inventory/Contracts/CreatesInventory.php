<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Data\CreateInventoryData;
use App\Modules\Inventory\Models\Inventory;

interface CreatesInventory
{
    public function handle(CreateInventoryData $data): Inventory;
}

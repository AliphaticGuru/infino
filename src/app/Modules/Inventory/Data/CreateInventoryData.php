<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Data;

use App\Modules\Core\Data\Data;

final readonly class CreateInventoryData extends Data
{
    public function __construct(
        public int $variantId,
        public string $location = 'default',
        public int $quantity = 0,
    ) {}
}

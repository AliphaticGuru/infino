<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Data;

use App\Modules\Core\Data\Data;

final readonly class AdjustStockData extends Data
{
    public function __construct(
        public int $quantityDelta,
        public string $reason,
        public ?string $reference = null,
    ) {}
}

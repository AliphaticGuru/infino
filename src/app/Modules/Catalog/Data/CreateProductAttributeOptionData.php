<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class CreateProductAttributeOptionData extends Data
{
    public function __construct(
        public string $code,
        public string $label,
        public int $position = 0,
        public bool $isActive = true,
    ) {}
}

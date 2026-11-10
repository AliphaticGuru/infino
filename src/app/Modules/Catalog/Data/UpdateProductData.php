<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class UpdateProductData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $isActive = null,
    ) {}
}

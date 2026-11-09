<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class CreateProductData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {
    }
}
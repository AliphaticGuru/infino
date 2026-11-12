<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class UpdateProductAttributeData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?bool $isVariantDimension = null,
        public ?int $position = null,
        public ?bool $isActive = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Support\AttributeType;
use App\Modules\Core\Data\Data;

final readonly class CreateProductAttributeData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public AttributeType $type = AttributeType::Select,
        public bool $isVariantDimension = true,
        public int $position = 0,
        public bool $isActive = true,
    ) {}
}

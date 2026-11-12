<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class UpdateProductAttributeOptionData extends Data
{
    public function __construct(
        public ?string $label = null,
        public ?int $position = null,
        public ?bool $isActive = null,
    ) {}
}

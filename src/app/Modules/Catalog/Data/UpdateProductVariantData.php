<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class UpdateProductVariantData extends Data
{
    /**
     * @param  array<int, int>|null  $attributeOptions  Attribute ID => option ID, or null to leave selections unchanged.
     */
    public function __construct(
        public ?string $sku = null,
        public ?string $label = null,
        public ?string $variantKey = null,
        public ?array $attributeOptions = null,
    ) {}
}

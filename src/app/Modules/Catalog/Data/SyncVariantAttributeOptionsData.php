<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class SyncVariantAttributeOptionsData extends Data
{
    /**
     * @param  array<int, int>  $attributeOptions  Attribute ID => option ID.
     * @param  string|null  $expectedKey  Manual key that must match the derived key, if supplied.
     */
    public function __construct(
        public array $attributeOptions = [],
        public ?string $expectedKey = null,
    ) {}
}

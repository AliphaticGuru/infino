<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class RepositionProductMediaData extends Data
{
    /**
     * @param  int|null  $productVariantId  Null for the product gallery.
     * @param  list<int>  $mediaIds  Complete, duplicate-free ID set for one gallery.
     */
    public function __construct(
        public ?int $productVariantId,
        /** @var list<int> */
        public array $mediaIds,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class UpdateProductMediaData extends Data
{
    /**
     * @param  string|null  $altText  New alt text, or null to clear it.
     */
    public function __construct(
        public ?string $altText = null,
    ) {}
}

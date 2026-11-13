<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;
use Illuminate\Http\UploadedFile;

final readonly class AddProductMediaData extends Data
{
    public function __construct(
        public UploadedFile $file,
        public ?int $productVariantId = null,
        public ?string $altText = null,
        public bool $isPrimary = false,
    ) {}
}

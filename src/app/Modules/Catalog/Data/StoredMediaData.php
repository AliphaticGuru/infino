<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Data;

use App\Modules\Core\Data\Data;

final readonly class StoredMediaData extends Data
{
    public function __construct(
        public string $disk,
        public string $path,
        public ?string $originalName,
        public ?string $mimeType,
        public ?int $size,
        public ?int $width,
        public ?int $height,
    ) {}
}

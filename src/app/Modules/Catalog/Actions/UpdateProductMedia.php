<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\UpdatesProductMedia;
use App\Modules\Catalog\Data\UpdateProductMediaData;
use App\Modules\Catalog\Models\ProductMedia;
use Illuminate\Support\Facades\DB;

final class UpdateProductMedia implements UpdatesProductMedia
{
    public function handle(ProductMedia $media, UpdateProductMediaData $data): ProductMedia
    {
        $altText = $this->normalizeAltText($data->altText);

        return DB::transaction(function () use ($media, $altText): ProductMedia {
            $current = ProductMedia::query()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $current->update([
                'alt_text' => $altText,
            ]);

            return $current->fresh();
        });
    }

    private function normalizeAltText(?string $altText): ?string
    {
        if ($altText === null) {
            return null;
        }

        $altText = trim(strip_tags($altText));

        if ($altText === '') {
            return null;
        }

        return mb_substr($altText, 0, 255);
    }
}

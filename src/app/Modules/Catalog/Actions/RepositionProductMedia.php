<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\RepositionsProductMedia;
use App\Modules\Catalog\Data\RepositionProductMediaData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RepositionProductMedia implements RepositionsProductMedia
{
    /**
     * @return Collection<int, ProductMedia>
     */
    public function handle(Product $product, RepositionProductMediaData $data): Collection
    {
        $current = Product::query()
            ->whereKey($product->getKey())
            ->firstOrFail();

        $variant = null;

        if ($data->productVariantId !== null) {
            $variant = ProductVariant::query()
                ->whereKey($data->productVariantId)
                ->where('product_id', $current->getKey())
                ->first();

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'The selected variant does not belong to this product.',
                ]);
            }
        }

        return DB::transaction(function () use ($current, $variant, $data): Collection {
            $gallery = ($variant !== null
                ? $variant->media()->lockForUpdate()->get()
                : $current->media()->lockForUpdate()->get()
            )->keyBy('id');

            $expectedIds = $gallery->keys()->map(static fn (int $id): int => $id)->sort()->values()->all();
            $providedIds = array_map(static fn (int $id): int => $id, $data->mediaIds);
            sort($providedIds);

            if ($expectedIds !== $providedIds) {
                throw ValidationException::withMessages([
                    'media_ids' => 'The media set must be the complete, duplicate-free set for this gallery.',
                ]);
            }

            foreach ($data->mediaIds as $position => $mediaId) {
                $media = $gallery->get($mediaId);

                if ($media === null || (int) $media->position !== $position) {
                    $media->update(['position' => $position]);
                    $media->refresh();
                }
            }

            return $gallery->sortBy('position')->values();
        });
    }
}

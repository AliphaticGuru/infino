<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\RepositionsProductVariants;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RepositionProductVariants implements RepositionsProductVariants
{
    /**
     * @param  array<int, int>  $variantIds
     * @return Collection<int, ProductVariant>
     */
    public function handle(Product $product, array $variantIds): Collection
    {
        $variantIds = array_values(array_map(static fn (int|string $id): int => (int) $id, $variantIds));

        if (count($variantIds) !== count(array_unique($variantIds))) {
            throw ValidationException::withMessages([
                'variants' => 'The variant order must not contain duplicate variants.',
            ]);
        }

        return DB::transaction(function () use ($product, $variantIds): Collection {
            $current = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $variants = $current->variants()
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $existingIds = $variants->pluck('id')->all();

            if (
                count($variantIds) !== count($existingIds)
                || array_diff($existingIds, $variantIds) !== []
                || array_diff($variantIds, $existingIds) !== []
            ) {
                throw ValidationException::withMessages([
                    'variants' => 'The variant order must contain every variant of this product exactly once.',
                ]);
            }

            $variantsById = $variants->keyBy('id');

            foreach ($variantIds as $position => $variantId) {
                $variant = $variantsById->get($variantId);

                if ($variant->position === $position) {
                    continue;
                }

                $variant->position = $position;
                $variant->save();
            }

            return $current->variants()
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        });
    }
}

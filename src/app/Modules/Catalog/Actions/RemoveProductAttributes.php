<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\RemovesProductAttributes;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RemoveProductAttributes implements RemovesProductAttributes
{
    /**
     * @param  array<int, int>  $attributeIds
     */
    public function handle(Product $product, array $attributeIds): Product
    {
        return DB::transaction(function () use ($product, $attributeIds): Product {
            $current = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $validated = Validator::make(
                ['attribute_ids' => array_values($attributeIds)],
                [
                    'attribute_ids' => ['array'],
                    'attribute_ids.*' => ['integer', 'distinct'],
                ],
            )->validate();

            $ids = $validated['attribute_ids'];

            $this->assertUnselected($current, $ids);

            $current->productAttributes()->detach($ids);

            return $current->refresh();
        });
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function assertUnselected(Product $product, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $hasSelections = ProductVariantAttributeOption::query()
            ->whereIn('product_attribute_id', $ids)
            ->whereHas(
                'variant',
                static fn (Builder $query) => $query->where('product_id', $product->getKey()),
            )
            ->exists();

        if ($hasSelections) {
            throw ValidationException::withMessages([
                'attribute_ids' => 'An attribute selected by a variant of this product cannot be removed from the product.',
            ]);
        }
    }
}

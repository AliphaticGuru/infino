<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\SyncsProductAttributes;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SyncProductAttributes implements SyncsProductAttributes
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
                    'attribute_ids.*' => ['integer', 'distinct', 'exists:product_attributes,id'],
                ],
            )->validate();

            $ids = $validated['attribute_ids'];

            $this->assertAssignable($ids);

            $removed = $current->productAttributes()
                ->whereNotIn('product_attributes.id', $ids)
                ->pluck('product_attributes.id')
                ->all();

            $this->assertUnselected($current, $removed);

            $current->productAttributes()->sync($ids);

            return $current->refresh();
        });
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function assertAssignable(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $attributes = ProductAttribute::query()->whereKey($ids)->get();

        foreach ($attributes as $attribute) {
            if (! $attribute->is_active) {
                throw ValidationException::withMessages([
                    'attribute_ids' => "The attribute [{$attribute->code}] is inactive and cannot be assigned.",
                ]);
            }

            if (! $attribute->is_variant_dimension) {
                throw ValidationException::withMessages([
                    'attribute_ids' => "The attribute [{$attribute->code}] is not a variant dimension and cannot be assigned.",
                ]);
            }
        }
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

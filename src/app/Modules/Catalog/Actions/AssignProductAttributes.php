<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\AssignsProductAttributes;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AssignProductAttributes implements AssignsProductAttributes
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

            $current->productAttributes()->syncWithoutDetaching($ids);

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
}

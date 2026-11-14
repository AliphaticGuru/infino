<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\SyncsVariantAttributeOptions;
use App\Modules\Catalog\Data\SyncVariantAttributeOptionsData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use App\Modules\Catalog\Support\VariantKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncVariantAttributeOptions implements SyncsVariantAttributeOptions
{
    public function handle(ProductVariant $variant, SyncVariantAttributeOptionsData $data): ProductVariant
    {
        $attributeOptions = $this->normalize($data->attributeOptions);

        return DB::transaction(function () use ($variant, $data, $attributeOptions): ProductVariant {
            $current = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::query()
                ->whereKey($current->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Collection<int, ProductAttribute> $dimensions */
            $dimensions = $product->productAttributes()
                ->where('product_attributes.is_variant_dimension', true)
                ->orderBy('product_attributes.position')
                ->orderBy('product_attributes.code')
                ->get();

            $this->assertComplete($dimensions, $attributeOptions);

            $attributes = $dimensions->keyBy('id');

            /** @var Collection<int, ProductAttributeOption> $options */
            $options = ProductAttributeOption::query()
                ->whereIn('id', array_values($attributeOptions))
                ->get()
                ->keyBy('id');

            /** @var Collection<int, ProductVariantAttributeOption> $existing */
            $existing = $current->attributeOptions()->get()->keyBy('product_attribute_id');

            $pairs = [];

            foreach ($attributeOptions as $attributeId => $optionId) {
                $attribute = $attributes->get($attributeId);
                $option = $options->get($optionId);

                if ($attribute === null) {
                    throw ValidationException::withMessages([
                        'attribute_options' => 'The selected attribute is not a variant dimension of this product.',
                    ]);
                }

                if ($option === null || (int) $option->product_attribute_id !== $attributeId) {
                    throw ValidationException::withMessages([
                        'attribute_options' => "The selected option does not belong to the attribute [{$attribute->code}].",
                    ]);
                }

                $currentSelection = $existing->get($attributeId);
                $unchanged = $currentSelection !== null
                    && (int) $currentSelection->product_attribute_option_id === $optionId;

                if (! $unchanged && ! $attribute->is_active) {
                    throw ValidationException::withMessages([
                        'attribute_options' => "The attribute [{$attribute->code}] is inactive and cannot be newly selected.",
                    ]);
                }

                if (! $unchanged && ! $option->is_active) {
                    throw ValidationException::withMessages([
                        'attribute_options' => "The option [{$option->code}] of attribute [{$attribute->code}] is inactive and cannot be newly selected.",
                    ]);
                }

                $pairs[] = [
                    'attributeCode' => $attribute->code,
                    'optionCode' => $option->code,
                    'attributePosition' => (int) $attribute->position,
                ];
            }

            $derivedKey = $attributeOptions === []
                ? $data->expectedKey
                : VariantKey::fromPairs($pairs);

            if ($derivedKey === '') {
                $derivedKey = null;
            }

            if (
                $attributeOptions !== []
                && $data->expectedKey !== null
                && $data->expectedKey !== $derivedKey
            ) {
                throw ValidationException::withMessages([
                    'variant_key' => 'The variant key must match the selected attribute options.',
                ]);
            }

            if ($derivedKey !== null) {
                $duplicate = ProductVariant::query()
                    ->where('product_id', $current->product_id)
                    ->where('variant_key', $derivedKey)
                    ->whereKeyNot($current->getKey())
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'variant_key' => 'This combination of attribute options is already used by another variant of this product.',
                    ]);
                }
            }

            $current->attributeOptions()->delete();

            foreach ($attributeOptions as $attributeId => $optionId) {
                $current->attributeOptions()->create([
                    'product_attribute_id' => $attributeId,
                    'product_attribute_option_id' => $optionId,
                ]);
            }

            $current->variant_key = $derivedKey;
            $current->save();

            return $current->refresh();
        });
    }

    /**
     * @param  array<mixed, mixed>  $attributeOptions
     * @return array<int, int>
     */
    private function normalize(array $attributeOptions): array
    {
        $normalized = [];

        foreach ($attributeOptions as $attributeId => $optionId) {
            if (! $this->isIntegerLike($attributeId) || ! $this->isIntegerLike($optionId)) {
                throw ValidationException::withMessages([
                    'attribute_options' => 'Attribute and option IDs must be positive integers.',
                ]);
            }

            $attributeId = (int) $attributeId;
            $optionId = (int) $optionId;

            if ($attributeId <= 0 || $optionId <= 0) {
                throw ValidationException::withMessages([
                    'attribute_options' => 'Attribute and option IDs must be positive integers.',
                ]);
            }

            if (array_key_exists($attributeId, $normalized)) {
                throw ValidationException::withMessages([
                    'attribute_options' => 'A variant may select only one option per attribute.',
                ]);
            }

            $normalized[$attributeId] = $optionId;
        }

        return $normalized;
    }

    /**
     * @param  Collection<int, ProductAttribute>  $dimensions
     * @param  array<int, int>  $attributeOptions
     */
    private function assertComplete(Collection $dimensions, array $attributeOptions): void
    {
        $dimensionIds = $dimensions->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $providedIds = array_keys($attributeOptions);

        sort($dimensionIds);
        sort($providedIds);

        if ($dimensionIds !== $providedIds) {
            throw ValidationException::withMessages([
                'attribute_options' => 'A variant must select exactly one option for each variant dimension assigned to its product.',
            ]);
        }
    }

    private function isIntegerLike(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }
}

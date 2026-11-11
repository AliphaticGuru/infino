<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProductVariants;
use App\Modules\Catalog\Data\CreateProductVariantData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\UniqueSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateProductVariant implements CreatesProductVariants
{
    public function handle(Product $product, CreateProductVariantData $data): ProductVariant
    {
        $validated = Validator::make(
            [
                'sku' => $data->sku,
                'label' => $data->label,
                'variant_key' => $data->variantKey,
            ],
            [
                'sku' => ['nullable', 'string', 'max:100', 'unique:product_variants,sku'],
                'label' => ['nullable', 'string', 'max:255'],
                'variant_key' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('product_variants', 'variant_key')
                        ->where('product_id', $product->getKey()),
                ],
            ],
        )->validate();

        return DB::transaction(function () use ($product, $data, $validated): ProductVariant {
            $current = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $current->variants()
                ->lockForUpdate()
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $isFirstVariant = $existing->isEmpty();
            $position = $existing->count();

            $variant = $current->variants()->create([
                'sku' => $validated['sku'] ?? UniqueSku::generate('SKU-'.$current->id.'-'.($position + 1)),
                'label' => $validated['label'] ?? ($isFirstVariant ? 'Default' : null),
                'variant_key' => $validated['variant_key'] ?? null,
                'is_active' => $isFirstVariant ? true : $data->isActive,
                'position' => $position,
            ]);

            $this->normalizePositions($current);

            return $variant->refresh();
        });
    }

    private function normalizePositions(Product $product): void
    {
        $variants = $product->variants()
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($variants as $position => $variant) {
            if ($variant->position === $position) {
                continue;
            }

            $variant->position = $position;
            $variant->save();
        }
    }
}

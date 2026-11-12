<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\SyncsVariantAttributeOptions;
use App\Modules\Catalog\Contracts\UpdatesProductVariants;
use App\Modules\Catalog\Data\SyncVariantAttributeOptionsData;
use App\Modules\Catalog\Data\UpdateProductVariantData;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateProductVariant implements UpdatesProductVariants
{
    public function __construct(
        private readonly SyncsVariantAttributeOptions $syncVariantAttributeOptions,
    ) {}

    public function handle(ProductVariant $variant, UpdateProductVariantData $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data): ProductVariant {
            $current = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $input = [];
            $rules = [];

            if ($data->sku !== null) {
                $input['sku'] = $data->sku;
                $rules['sku'] = [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('product_variants', 'sku')->ignore($current->getKey()),
                ];
            }

            if ($data->label !== null) {
                $input['label'] = $data->label;
                $rules['label'] = ['required', 'string', 'max:255'];
            }

            if ($data->variantKey !== null) {
                $input['variant_key'] = $data->variantKey;
                $rules['variant_key'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('product_variants', 'variant_key')
                        ->where('product_id', $current->product_id)
                        ->ignore($current->getKey()),
                ];
            }

            $validated = Validator::make($input, $rules)->validate();

            if ($validated !== []) {
                $current->fill($validated)->save();
            }

            if ($data->attributeOptions !== null) {
                $current = $this->syncVariantAttributeOptions->handle(
                    $current,
                    new SyncVariantAttributeOptionsData($data->attributeOptions, $data->variantKey),
                );
            }

            return $current->refresh();
        });
    }
}

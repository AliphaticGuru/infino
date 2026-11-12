<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\UpdatesProductAttributes;
use App\Modules\Catalog\Data\UpdateProductAttributeData;
use App\Modules\Catalog\Models\ProductAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateProductAttribute implements UpdatesProductAttributes
{
    public function handle(ProductAttribute $attribute, UpdateProductAttributeData $data): ProductAttribute
    {
        return DB::transaction(function () use ($attribute, $data): ProductAttribute {
            $current = ProductAttribute::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $input = [];
            $rules = [];

            if ($data->name !== null) {
                $input['name'] = $data->name;
                $rules['name'] = ['required', 'string', 'max:255'];
            }

            if ($data->isVariantDimension !== null) {
                $input['is_variant_dimension'] = $data->isVariantDimension;
                $rules['is_variant_dimension'] = ['boolean'];
            }

            if ($data->position !== null) {
                $input['position'] = $data->position;
                $rules['position'] = ['integer', 'min:0'];
            }

            if ($data->isActive !== null) {
                $input['is_active'] = $data->isActive;
                $rules['is_active'] = ['boolean'];
            }

            $validated = Validator::make($input, $rules)->validate();

            if (($validated['is_variant_dimension'] ?? true) === false) {
                $isReferenced = $current->products()->exists()
                    || $current->variantSelections()->exists();

                if ($isReferenced) {
                    throw ValidationException::withMessages([
                        'is_variant_dimension' => 'An attribute that is assigned to products or selected by variants cannot stop being a variant dimension.',
                    ]);
                }
            }

            if ($validated !== []) {
                $current->fill($validated)->save();
            }

            return $current->refresh();
        });
    }
}

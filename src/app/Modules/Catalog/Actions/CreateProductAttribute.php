<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProductAttributes;
use App\Modules\Catalog\Data\CreateProductAttributeData;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Support\AttributeType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateProductAttribute implements CreatesProductAttributes
{
    public function handle(CreateProductAttributeData $data): ProductAttribute
    {
        $validated = Validator::make(
            [
                'code' => $data->code,
                'name' => $data->name,
                'type' => $data->type->value,
                'is_variant_dimension' => $data->isVariantDimension,
                'position' => $data->position,
                'is_active' => $data->isActive,
            ],
            [
                'code' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                    'unique:product_attributes,code',
                ],
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(AttributeType::values())],
                'is_variant_dimension' => ['boolean'],
                'position' => ['integer', 'min:0'],
                'is_active' => ['boolean'],
            ],
        )->validate();

        return ProductAttribute::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'is_variant_dimension' => $validated['is_variant_dimension'],
            'position' => $validated['position'],
            'is_active' => $validated['is_active'],
        ]);
    }
}

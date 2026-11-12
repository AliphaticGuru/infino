<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProductAttributeOptions;
use App\Modules\Catalog\Data\CreateProductAttributeOptionData;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CreateProductAttributeOption implements CreatesProductAttributeOptions
{
    public function handle(ProductAttribute $attribute, CreateProductAttributeOptionData $data): ProductAttributeOption
    {
        return DB::transaction(function () use ($attribute, $data): ProductAttributeOption {
            $current = ProductAttribute::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $validated = Validator::make(
                [
                    'code' => $data->code,
                    'label' => $data->label,
                    'position' => $data->position,
                    'is_active' => $data->isActive,
                ],
                [
                    'code' => [
                        'required',
                        'string',
                        'max:100',
                        'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                        Rule::unique('product_attribute_options', 'code')
                            ->where('product_attribute_id', $current->getKey()),
                    ],
                    'label' => ['required', 'string', 'max:255'],
                    'position' => ['integer', 'min:0'],
                    'is_active' => ['boolean'],
                ],
            )->validate();

            return $current->options()->create([
                'code' => $validated['code'],
                'label' => $validated['label'],
                'position' => $validated['position'],
                'is_active' => $validated['is_active'],
            ]);
        });
    }
}

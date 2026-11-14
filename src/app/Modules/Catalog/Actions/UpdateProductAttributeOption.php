<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\UpdatesProductAttributeOptions;
use App\Modules\Catalog\Data\UpdateProductAttributeOptionData;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class UpdateProductAttributeOption implements UpdatesProductAttributeOptions
{
    public function handle(ProductAttributeOption $option, UpdateProductAttributeOptionData $data): ProductAttributeOption
    {
        return DB::transaction(function () use ($option, $data): ProductAttributeOption {
            $current = ProductAttributeOption::query()
                ->whereKey($option->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $input = [];
            $rules = [];

            if ($data->label !== null) {
                $input['label'] = $data->label;
                $rules['label'] = ['required', 'string', 'max:255'];
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

            if ($validated !== []) {
                $current->fill($validated)->save();
            }

            return $current->refresh();
        });
    }
}

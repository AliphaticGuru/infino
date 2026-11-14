<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\RepositionsProductAttributeOptions;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RepositionProductAttributeOptions implements RepositionsProductAttributeOptions
{
    /**
     * @param  array<int, int>  $optionIds
     * @return Collection<int, ProductAttributeOption>
     */
    public function handle(ProductAttribute $attribute, array $optionIds): Collection
    {
        $optionIds = array_values(array_map(static fn (int|string $id): int => (int) $id, $optionIds));

        if (count($optionIds) !== count(array_unique($optionIds))) {
            throw ValidationException::withMessages([
                'options' => 'The option order must not contain duplicate options.',
            ]);
        }

        return DB::transaction(function () use ($attribute, $optionIds): Collection {
            $current = ProductAttribute::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $options = $current->options()
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $existingIds = $options->pluck('id')->all();

            if (
                count($optionIds) !== count($existingIds)
                || array_diff($existingIds, $optionIds) !== []
                || array_diff($optionIds, $existingIds) !== []
            ) {
                throw ValidationException::withMessages([
                    'options' => 'The option order must contain every option of this attribute exactly once.',
                ]);
            }

            $optionsById = $options->keyBy('id');

            foreach ($optionIds as $position => $optionId) {
                $option = $optionsById->get($optionId);

                if ($option->position === $position) {
                    continue;
                }

                $option->position = $position;
                $option->save();
            }

            return $current->options()
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        });
    }
}

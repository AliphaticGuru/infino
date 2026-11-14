<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\DeletesProductAttributeOptions;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteProductAttributeOption implements DeletesProductAttributeOptions
{
    public function handle(ProductAttributeOption $option): void
    {
        DB::transaction(function () use ($option): void {
            $current = ProductAttributeOption::query()
                ->whereKey($option->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->variantSelections()->exists()) {
                throw ValidationException::withMessages([
                    'option' => 'An option selected by product variants cannot be deleted. Deactivate it instead.',
                ]);
            }

            $current->delete();
        });
    }
}

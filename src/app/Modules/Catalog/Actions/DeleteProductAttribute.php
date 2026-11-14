<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\DeletesProductAttributes;
use App\Modules\Catalog\Models\ProductAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteProductAttribute implements DeletesProductAttributes
{
    public function handle(ProductAttribute $attribute): void
    {
        DB::transaction(function () use ($attribute): void {
            $current = ProductAttribute::query()
                ->whereKey($attribute->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->products()->exists()) {
                throw ValidationException::withMessages([
                    'attribute' => 'An attribute assigned to products cannot be deleted. Deactivate it instead.',
                ]);
            }

            if ($current->variantSelections()->exists()) {
                throw ValidationException::withMessages([
                    'attribute' => 'An attribute selected by product variants cannot be deleted. Deactivate it instead.',
                ]);
            }

            $current->delete();
        });
    }
}

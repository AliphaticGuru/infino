<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\TransitionsProductVariantStatus;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionProductVariantStatus implements TransitionsProductVariantStatus
{
    public function activate(ProductVariant $variant): ProductVariant
    {
        return DB::transaction(function () use ($variant): ProductVariant {
            $current = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($current->is_active) {
                throw ValidationException::withMessages([
                    'variant' => 'The variant is already active.',
                ]);
            }

            $current->is_active = true;
            $current->save();

            return $current->refresh();
        });
    }

    public function deactivate(ProductVariant $variant): ProductVariant
    {
        return DB::transaction(function () use ($variant): ProductVariant {
            $current = ProductVariant::query()
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $current->is_active) {
                throw ValidationException::withMessages([
                    'variant' => 'The variant is already inactive.',
                ]);
            }

            if ($current->isDefault()) {
                throw ValidationException::withMessages([
                    'variant' => 'The default variant cannot be deactivated.',
                ]);
            }

            $hasAnotherActiveVariant = ProductVariant::query()
                ->where('product_id', $current->product_id)
                ->where('is_active', true)
                ->whereKeyNot($current->getKey())
                ->exists();

            if (! $hasAnotherActiveVariant) {
                throw ValidationException::withMessages([
                    'variant' => 'The last active variant cannot be deactivated.',
                ]);
            }

            $current->is_active = false;
            $current->save();

            return $current->refresh();
        });
    }
}

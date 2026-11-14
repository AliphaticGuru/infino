<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Contracts\RemovesVariantPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Models\VariantPriceChange;
use Illuminate\Support\Facades\DB;

/**
 * Action for removing a variant's current price.
 *
 * - Locks the price row appropriately.
 * - Records a 'removed' history event before deleting the current price.
 * - Preserves the history record.
 * - Executes transactionally.
 * - Supports nullable actor attribution.
 */
final class RemoveVariantPrice implements RemovesVariantPrice
{
    public function handle(int $variantId, string $currency, ?int $actorId = null): VariantPriceChange
    {
        // Find the current price
        $price = VariantPrice::where('variant_id', $variantId)
            ->where('currency', $currency)
            ->firstOrFail();

        return DB::transaction(function () use ($variantId, $currency, $actorId, $price) {
            // Lock the price row
            $price = VariantPrice::where('id', $price->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Record the removal history event BEFORE deleting the current price
            $change = VariantPriceChange::create([
                'variant_id' => $variantId,
                'currency' => $currency,
                'change_type' => 'removed',
                'old_amount_minor' => $price->amount_minor,
                'new_amount_minor' => null,
                'old_compare_at_amount_minor' => $price->compare_at_amount_minor,
                'new_compare_at_amount_minor' => null,
                'changed_by' => $actorId,
            ]);

            // Delete the current price row
            $price->delete();

            return $change;
        });
    }
}

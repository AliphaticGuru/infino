<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Contracts\SetsVariantPrice;
use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Models\VariantPriceChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action for creating or updating a variant's price.
 *
 * - Validates currency, amounts and compare-at rules.
 * - Locks the variant row for the write decision.
 * - Creates or updates the current price using the unique (variant_id, currency) constraint.
 * - Writes a history event (created or updated) in the same transaction.
 * - Attributes the change to the supplied actor where available.
 */
final class SetVariantPrice implements SetsVariantPrice
{
    public function handle(int $variantId, string $currency, int $amountMinor, ?int $compareAtAmountMinor = null, ?int $actorId = null): VariantPrice
    {
        // Validate currency is supported
        $exponents = config('pricing.exponents');
        if (! isset($exponents[$currency])) {
            throw ValidationException::withMessages([
                'currency' => ["Currency [{$currency}] is not supported."],
            ]);
        }

        // Validate amounts
        if ($amountMinor < 0) {
            throw ValidationException::withMessages([
                'amountMinor' => ['Amount must be non-negative.'],
            ]);
        }

        // Validate compare-at amount if supplied
        if ($compareAtAmountMinor !== null && $compareAtAmountMinor <= $amountMinor) {
            throw ValidationException::withMessages([
                'compareAtAmountMinor' => ['Compare-at price must be strictly greater than the base price.'],
            ]);
        }

        $variant = ProductVariant::findOrFail($variantId);

        // Check if a price already exists for this variant and currency BEFORE creating/updating
        $existingPrice = VariantPrice::where('variant_id', $variant->id)
            ->where('currency', $currency)
            ->first();

        return DB::transaction(function () use ($variant, $currency, $amountMinor, $compareAtAmountMinor, $actorId, $existingPrice) {
            // Lock the variant row
            $variant = ProductVariant::where('id', $variant->id)->lockForUpdate()->firstOrFail();

            // Upsert the current price - use updateOrCreate with the unique constraint
            $price = VariantPrice::updateOrCreate(
                ['variant_id' => $variant->id, 'currency' => $currency],
                ['amount_minor' => $amountMinor, 'compare_at_amount_minor' => $compareAtAmountMinor]
            );

            // Determine the change type:
            // - 'created' if no price existed before
            // - 'updated' if a price already existed
            $changeType = $existingPrice ? 'updated' : 'created';

            // Write the history event in the same transaction
            VariantPriceChange::create([
                'variant_id' => $variant->id,
                'currency' => $currency,
                'change_type' => $changeType,
                'old_amount_minor' => $existingPrice?->amount_minor,
                'new_amount_minor' => $price->amount_minor,
                'old_compare_at_amount_minor' => $existingPrice?->compare_at_amount_minor,
                'new_compare_at_amount_minor' => $price->compare_at_amount_minor,
                'changed_by' => $actorId,
            ]);

            return $price;
        });
    }
}

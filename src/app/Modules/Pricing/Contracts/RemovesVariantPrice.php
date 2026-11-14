<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Contracts;

use App\Modules\Pricing\Models\VariantPriceChange;
use Illuminate\Validation\ValidationException;

/**
 * Contract for removing a variant's current price.
 *
 * The implementation must:
 * - Locate the current price row.
 * - Record a 'removed' history event before deleting the current price.
 * - Preserve the history record.
 * - Execute transactionally.
 * - Support nullable actor attribution.
 *
 * @param  int  $variantId  Product variant ID
 * @param  string  $currency  ISO 4217 currency code
 * @param  int|null  $actorId  User ID attributing the removal, or null
 * @return VariantPriceChange The history record that was created
 *
 * @throws ValidationException
 */
interface RemovesVariantPrice
{
    /**
     * Remove the current price for a variant and currency.
     *
     * @param  int  $variantId  Product variant ID
     * @param  string  $currency  ISO 4217 currency code
     * @param  int|null  $actorId  User ID attributing the removal, or null
     *
     * @throws ValidationException
     */
    public function handle(int $variantId, string $currency, ?int $actorId = null): VariantPriceChange;
}

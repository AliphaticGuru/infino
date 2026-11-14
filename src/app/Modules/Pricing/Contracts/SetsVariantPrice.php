<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Contracts;

use App\Modules\Pricing\Models\VariantPrice;
use App\Modules\Pricing\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Contract for creating or updating a variant's price.
 *
 * The implementation must:
 * - Validate the currency is supported.
 * - Validate amounts using the Money value object and currency exponents.
 * - Ensure compare_at_amount_minor > amount_minor when supplied.
 * - Operate transactionally and lock the variant/price row.
 * - Record a history event (created or updated) in the same transaction.
 * - Attribute the change to the supplied actor where available.
 */
interface SetsVariantPrice
{
    /**
     * Set the current price for a variant and currency.
     *
     * @param  int  $variantId  Product variant ID
     * @param  string  $currency  ISO 4217 currency code
     * @param  int  $amountMinor  Amount in minor units (validated through Money)
     * @param  int|null  $compareAtAmountMinor  Compare-at amount in minor units, or null
     * @param  int|null  $actorId  User ID attributing the change, or null
     *
     * @throws ValidationException
     */
    public function handle(int $variantId, string $currency, int $amountMinor, ?int $compareAtAmountMinor = null, ?int $actorId = null): VariantPrice;
}

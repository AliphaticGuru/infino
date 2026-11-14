<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Data;

use App\Modules\Core\Data\Data;

/**
 * Data object for setting a variant price.
 *
 * @param  int  $variantId  Product variant ID
 * @param  string  $currency  ISO 4217 currency code
 * @param  int  $amountMinor  Amount in minor units (validated through Money)
 * @param  int|null  $compareAtAmountMinor  Compare-at amount in minor units, or null
 * @param  int|null  $actorId  User ID attributing the change, or null
 */
final readonly class SetVariantPriceData extends Data
{
    public function __construct(
        public int $variantId,
        public string $currency,
        public int $amountMinor,
        public ?int $compareAtAmountMinor = null,
        public ?int $actorId = null,
    ) {}
}

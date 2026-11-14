<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Actions;

use App\Modules\Pricing\Contracts\FindsVariantPrices;
use App\Modules\Pricing\Models\VariantPrice;

/**
 * Action for reading variant prices.
 *
 * Retrieves current prices without modifying state.
 */
final class FindVariantPrices implements FindsVariantPrices
{
    /**
     * @param  list<VariantPrice>  $prices
     */
    public function find(int $variantId, string $currency): ?VariantPrice
    {
        return VariantPrice::where('variant_id', $variantId)
            ->where('currency', $currency)
            ->first();
    }

    /**
     * Find current prices for a variant.
     *
     * @return list<VariantPrice>
     */
    public function forVariant(int $variantId): array
    {
        return VariantPrice::where('variant_id', $variantId)
            ->orderBy('currency')
            ->get()
            ->toArray();
    }

    /**
     * Find current prices for a currency.
     *
     * @return list<VariantPrice>
     */
    public function forCurrency(string $currency): array
    {
        return VariantPrice::where('currency', $currency)
            ->orderBy('variant_id')
            ->get()
            ->toArray();
    }
}

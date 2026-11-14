<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Contracts;

use App\Modules\Pricing\Models\VariantPrice;

/**
 * Contract for reading variant prices.
 */
interface FindsVariantPrices
{
    /**
     * Find the current price for a variant and currency.
     */
    public function find(int $variantId, string $currency): ?VariantPrice;

    /**
     * Find current prices for a variant.
     *
     * @return array<int, VariantPrice>
     */
    public function forVariant(int $variantId): array;

    /**
     * Find current prices for a currency.
     *
     * @return array<int, VariantPrice>
     */
    public function forCurrency(string $currency): array;
}

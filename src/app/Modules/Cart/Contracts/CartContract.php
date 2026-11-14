<?php

declare(strict_types=1);

namespace App\Modules\Cart\Contracts;

use App\Modules\Cart\Models\CartItem;
use Illuminate\Support\Collection;

/**
 * Contract for cart operations.
 *
 * All cart read/write operations should go through this interface
 * to ensure consistent behavior and authorization.
 */
interface CartContract
{
    /**
     * Add a variant to the cart.
     *
     * @return bool true if item was added, false if variant is inactive/invalid
     */
    public function addVariant(int $variantId, int $quantity = 1, ?int $actorId = null): bool;

    /**
     * Update the quantity of a cart item.
     *
     * @return bool true if quantity was updated, false if item not found
     */
    public function updateQuantity(int $variantId, int $quantity): bool;

    /**
     * Remove a variant from the cart.
     *
     * @return bool true if item was removed, false if item not found
     */
    public function removeVariant(int $variantId): bool;

    /**
     * Get the current quantity of a variant in the cart.
     *
     * @return int quantity (0 if not in cart)
     */
    public function getQuantity(int $variantId): int;

    /**
     * Get all items in the cart.
     *
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection;

    /**
     * Get the cart total in minor units and currency.
     *
     * @return array{amount_minor: int, currency: string}
     */
    public function getTotalMinorAndCurrency(): array;

    /**
     * Check if a variant is in the cart.
     */
    public function hasVariant(int $variantId): bool;

    /**
     * Check if the cart is expired.
     */
    public function isExpired(): bool;
}

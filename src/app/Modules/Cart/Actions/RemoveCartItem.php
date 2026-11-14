<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;

/**
 * Remove a variant from the cart.
 */
final class RemoveCartItem
{
    public function handle(int $cartId, int $variantId): bool
    {
        $cart = Cart::query()->find($cartId);

        if ($cart === null) {
            return false;
        }

        return $cart->removeVariant($variantId);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use Illuminate\Validation\ValidationException;

/**
 * Update the quantity of a variant already in the cart.
 */
final class UpdateCartItemQuantity
{
    public function handle(int $cartId, int $variantId, int $quantity): bool
    {
        if ($quantity < 1 || $quantity > Cart::MAX_ITEM_QUANTITY) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be between 1 and '.Cart::MAX_ITEM_QUANTITY.'.'],
            ]);
        }

        $cart = Cart::query()->find($cartId);

        if ($cart === null) {
            return false;
        }

        if ($cart->isExpired()) {
            throw ValidationException::withMessages([
                'cart' => ['This cart has expired.'],
            ]);
        }

        return $cart->updateQuantity($variantId, $quantity);
    }
}

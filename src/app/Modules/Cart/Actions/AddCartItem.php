<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Models\Cart;
use Illuminate\Validation\ValidationException;

/**
 * Add a variant to a cart, enforcing variant, quantity, and expiry rules.
 *
 * The Cart model owns the cart operations; this action adapts them for
 * request/use-case layers that expect validation exceptions.
 */
final class AddCartItem
{
    public function handle(int $cartId, int $variantId, int $quantity = 1, ?int $actorId = null): bool
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

        if (! $cart->addVariant($variantId, $quantity, $actorId)) {
            throw ValidationException::withMessages([
                'variantId' => ['The selected variant does not exist, is inactive, or the quantity is invalid.'],
            ]);
        }

        return true;
    }
}

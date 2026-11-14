<?php

declare(strict_types=1);

use App\Foundation\Modules\ModuleRepository;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Providers\CartServiceProvider;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use App\Modules\Pricing\Contracts\SetsVariantPrice;
use Illuminate\Support\Facades\Schema;

function cartTestUser(): User
{
    return User::factory()->create();
}

function cartTestVariant(bool $active = true): ProductVariant
{
    return ProductVariant::factory()->create(['is_active' => $active]);
}

function cartTestPrice(ProductVariant $variant, int $amountMinor = 5000, ?int $compareAtMinor = null): void
{
    app(SetsVariantPrice::class)->handle($variant->id, 'NGN', $amountMinor, $compareAtMinor);
}

test('cart foundation is discovered and registered', function () {
    $modules = app(ModuleRepository::class)->all();
    $manifest = $modules->firstWhere('name', 'Cart');

    expect($manifest)->not->toBeNull()
        ->and($manifest->dependsOn('Core'))->toBeTrue()
        ->and($manifest->dependsOn('Catalog'))->toBeTrue()
        ->and($manifest->dependsOn('Pricing'))->toBeTrue()
        ->and(app()->getProviders(CartServiceProvider::class))->not->toBeEmpty();
});

test('carts table has the expected schema', function () {
    expect(Schema::hasTable('carts'))->toBeTrue()
        ->and(Schema::hasColumns('carts', [
            'id',
            'user_id',
            'token',
            'status',
            'expires_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('cart_items table has the expected schema', function () {
    expect(Schema::hasTable('cart_items'))->toBeTrue()
        ->and(Schema::hasColumns('cart_items', [
            'id',
            'cart_id',
            'variant_id',
            'quantity',
            'price_amount_minor',
            'price_currency',
            'compare_at_amount_minor',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('cart can be created for authenticated user', function () {
    $user = cartTestUser();

    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
        'expires_at' => now()->addHour(),
    ]);

    expect($cart->id)->not->toBeNull();
    expect($cart->user_id)->toBe($user->id);
    expect($cart->isAuthenticated())->toBeTrue();
    expect($cart->isGuest())->toBeFalse();
});

test('cart can be created as guest', function () {
    $cart = Cart::create([
        'token' => 'test-guest-token-'.uniqid(),
        'status' => 'active',
        'expires_at' => now()->addHour(),
    ]);

    expect($cart->id)->not->toBeNull();
    expect($cart->token)->not->toBeNull();
    expect($cart->isGuest())->toBeTrue();
    expect($cart->isAuthenticated())->toBeFalse();
});

test('guest carts are isolated by token', function () {
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $cartA = Cart::create(['token' => 'guest-token-a', 'status' => 'active', 'expires_at' => now()->addHour()]);
    $cartB = Cart::create(['token' => 'guest-token-b', 'status' => 'active', 'expires_at' => now()->addHour()]);

    expect($cartA->addVariant($variant->id, 1))->toBeTrue();

    expect($cartA->hasVariant($variant->id))->toBeTrue()
        ->and($cartB->hasVariant($variant->id))->toBeFalse()
        ->and($cartB->getItems())->toHaveCount(0);
});

test('authenticated carts are isolated by user', function () {
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $cartA = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $cartB = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);

    $cartA->addVariant($variant->id, 1);

    expect($cartA->hasVariant($variant->id))->toBeTrue()
        ->and($cartB->hasVariant($variant->id))->toBeFalse();
});

test('cart item rejects inactive variant', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $inactiveVariant = cartTestVariant(active: false);

    expect($cart->addVariant($inactiveVariant->id, 1))->toBeFalse()
        ->and($cart->getItems())->toHaveCount(0);
});

test('cart item rejects a variant that does not exist', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);

    expect($cart->addVariant(999999, 1))->toBeFalse()
        ->and($cart->getItems())->toHaveCount(0);
});

test('cart item adds valid variant', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $result = $cart->addVariant($variant->id, 1);

    expect($result)->toBeTrue();
    expect($cart->hasVariant($variant->id))->toBeTrue();
    expect($cart->getQuantity($variant->id))->toBe(1);
});

test('cart rejects invalid quantities', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    expect($cart->addVariant($variant->id, 0))->toBeFalse()
        ->and($cart->addVariant($variant->id, -1))->toBeFalse()
        ->and($cart->addVariant($variant->id, Cart::MAX_ITEM_QUANTITY + 1))->toBeFalse()
        ->and($cart->hasVariant($variant->id))->toBeFalse();

    $cart->addVariant($variant->id, 1);

    expect($cart->updateQuantity($variant->id, 0))->toBeFalse()
        ->and($cart->updateQuantity($variant->id, Cart::MAX_ITEM_QUANTITY + 1))->toBeFalse()
        ->and($cart->getQuantity($variant->id))->toBe(1);
});

test('cart duplicate variant updates quantity', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $cart->addVariant($variant->id, 1);
    $cart->addVariant($variant->id, 2);

    expect($cart->getQuantity($variant->id))->toBe(3)
        ->and($cart->getItems())->toHaveCount(1);
});

test('cart duplicate variant cannot exceed maximum quantity', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $cart->addVariant($variant->id, Cart::MAX_ITEM_QUANTITY);

    expect($cart->addVariant($variant->id, 1))->toBeFalse()
        ->and($cart->getQuantity($variant->id))->toBe(Cart::MAX_ITEM_QUANTITY);
});

test('cart removes variant', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $cart->addVariant($variant->id, 2);

    expect($cart->removeVariant($variant->id))->toBeTrue();

    expect($cart->hasVariant($variant->id))->toBeFalse();
    expect($cart->getQuantity($variant->id))->toBe(0);
});

test('cart removes a variant that is not present', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();

    expect($cart->removeVariant($variant->id))->toBeFalse();
});

test('cart updates quantity', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    $cart->addVariant($variant->id, 2);
    $cart->updateQuantity($variant->id, 5);

    expect($cart->getQuantity($variant->id))->toBe(5);
});

test('cart updates quantity for a variant that is not present', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();

    expect($cart->updateQuantity($variant->id, 5))->toBeFalse();
});

test('cart gets total minor and currency', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);

    $variant1 = cartTestVariant();
    $variant2 = cartTestVariant();
    cartTestPrice($variant1, 5000);
    cartTestPrice($variant2, 2500);

    $cart->addVariant($variant1->id, 2);
    $cart->addVariant($variant2->id, 3);

    $total = $cart->getTotalMinorAndCurrency();

    expect($total['amount_minor'])->toBe(17500);
    expect($total['currency'])->toBe('NGN');
});

test('empty cart total defaults to zero in the default currency', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);

    expect($cart->getTotalMinorAndCurrency())->toBe([
        'amount_minor' => 0,
        'currency' => 'NGN',
    ]);
});

test('cart snapshots the price at add-time', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant, 5000, 7000);

    $cart->addVariant($variant->id, 2);

    $item = $cart->getItems()->first();
    expect($item->price_amount_minor)->toBe(5000)
        ->and($item->compare_at_amount_minor)->toBe(7000);

    // A later price change must not rewrite the snapshot already in the cart.
    cartTestPrice($variant, 6000);

    $item = $cart->getItems()->first();
    expect($item->price_amount_minor)->toBe(5000)
        ->and($item->getSubtotalMinor())->toBe(10000)
        ->and($cart->getTotalMinorAndCurrency()['amount_minor'])->toBe(10000);
});

test('cart item getSubtotalMinor calculates correctly', function () {
    $cart = Cart::create(['user_id' => cartTestUser()->id, 'status' => 'active', 'expires_at' => now()->addHour()]);
    $variant = cartTestVariant();
    cartTestPrice($variant, 1500);

    $cart->addVariant($variant->id, 3);

    $item = $cart->getItems()->first();

    expect($item->getSubtotalMinor())->toBe(4500)
        ->and($item->getSubtotalCurrency())->toBe('NGN');
});

test('cart handles expired status', function () {
    $cart = Cart::create([
        'user_id' => cartTestUser()->id,
        'status' => 'active',
        'expires_at' => now()->subHour(),
    ]);

    expect($cart->isExpired())->toBeTrue();
});

test('cart handles non-expired status', function () {
    $cart = Cart::create([
        'user_id' => cartTestUser()->id,
        'status' => 'active',
        'expires_at' => now()->addHour(),
    ]);

    expect($cart->isExpired())->toBeFalse();
});

test('expired carts reject mutations', function () {
    $cart = Cart::create([
        'user_id' => cartTestUser()->id,
        'status' => 'active',
        'expires_at' => now()->subHour(),
    ]);
    $variant = cartTestVariant();
    cartTestPrice($variant);

    expect($cart->addVariant($variant->id, 1))->toBeFalse()
        ->and($cart->updateQuantity($variant->id, 2))->toBeFalse()
        ->and($cart->removeVariant($variant->id))->toBeFalse()
        ->and($cart->getItems())->toHaveCount(0);
});

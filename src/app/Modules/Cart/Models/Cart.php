<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Cart\Contracts\CartContract;
use App\Modules\Catalog\Contracts\FindsProductVariants;
use App\Modules\Pricing\Contracts\FindsVariantPrices;
use App\Modules\Pricing\Models\VariantPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $token
 * @property string $status
 * @property Carbon|null $expires_at
 * @property-read Collection<int, CartItem> $items
 */
final class Cart extends Model implements CartContract
{
    /**
     * Upper bound for a single line item quantity.
     *
     * Guards against accidental or abusive quantities while remaining far
     * above any realistic retail order.
     */
    public const MAX_ITEM_QUANTITY = 999;

    protected $table = 'carts';

    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'expires_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    public function userId(): ?int
    {
        return $this->user_id;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function isAuthenticated(): bool
    {
        return $this->user_id !== null;
    }

    public function addVariant(int $variantId, int $quantity = 1, ?int $actorId = null): bool
    {
        if (! $this->isValidQuantity($quantity) || $this->isExpired()) {
            return false;
        }

        if (! $this->isPurchasableVariant($variantId)) {
            return false;
        }

        $price = $this->currentPrice($variantId);

        return DB::transaction(function () use ($variantId, $quantity, $price): bool {
            $cart = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if ($cart === null || $cart->isExpired()) {
                return false;
            }

            $existingItem = CartItem::query()
                ->where('cart_id', $this->getKey())
                ->where('variant_id', $variantId)
                ->first();

            if ($existingItem !== null) {
                $newQuantity = $existingItem->quantity + $quantity;

                if (! $this->isValidQuantity($newQuantity)) {
                    return false;
                }

                $existingItem->quantity = $newQuantity;
                $existingItem->save();

                return true;
            }

            $item = new CartItem;
            $item->cart_id = $this->getKey();
            $item->variant_id = $variantId;
            $item->quantity = $quantity;

            // Snapshot the price at add-time. Later price changes must not
            // alter existing cart lines.
            $item->price_amount_minor = $price->amount_minor ?? 0;
            $item->price_currency = $price->currency ?? $this->defaultCurrency();
            $item->compare_at_amount_minor = $price?->compare_at_amount_minor;
            $item->save();

            return true;
        });
    }

    public function updateQuantity(int $variantId, int $quantity): bool
    {
        if (! $this->isValidQuantity($quantity) || $this->isExpired()) {
            return false;
        }

        return DB::transaction(function () use ($variantId, $quantity): bool {
            $cartItem = CartItem::query()
                ->where('cart_id', $this->getKey())
                ->where('variant_id', $variantId)
                ->first();

            if ($cartItem === null) {
                return false;
            }

            $cartItem->quantity = $quantity;
            $cartItem->save();

            return true;
        });
    }

    public function removeVariant(int $variantId): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return DB::transaction(function () use ($variantId): bool {
            $cartItem = CartItem::query()
                ->where('cart_id', $this->getKey())
                ->where('variant_id', $variantId)
                ->first();

            if ($cartItem === null) {
                return false;
            }

            $cartItem->delete();

            return true;
        });
    }

    public function getQuantity(int $variantId): int
    {
        $item = CartItem::query()
            ->where('cart_id', $this->getKey())
            ->where('variant_id', $variantId)
            ->first();

        return $item->quantity ?? 0;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return CartItem::query()
            ->where('cart_id', $this->getKey())
            ->with('variant')
            ->get();
    }

    /**
     * @return array{amount_minor: int, currency: string}
     */
    public function getTotalMinorAndCurrency(): array
    {
        $totalMinor = 0;
        $currency = $this->defaultCurrency();

        foreach ($this->items()->get() as $item) {
            $totalMinor += $item->getSubtotalMinor();
            $currency = $item->getSubtotalCurrency();
        }

        return [
            'amount_minor' => $totalMinor,
            'currency' => $currency,
        ];
    }

    public function hasVariant(int $variantId): bool
    {
        return CartItem::query()
            ->where('cart_id', $this->getKey())
            ->where('variant_id', $variantId)
            ->exists();
    }

    private function isValidQuantity(int $quantity): bool
    {
        return $quantity >= 1 && $quantity <= self::MAX_ITEM_QUANTITY;
    }

    private function isPurchasableVariant(int $variantId): bool
    {
        try {
            $variant = app(FindsProductVariants::class)->find($variantId);
        } catch (ModelNotFoundException) {
            return false;
        }

        return $variant->is_active === true;
    }

    private function currentPrice(int $variantId): ?VariantPrice
    {
        return app(FindsVariantPrices::class)->find($variantId, $this->defaultCurrency());
    }

    private function defaultCurrency(): string
    {
        return (string) config('pricing.default_currency', 'NGN');
    }
}

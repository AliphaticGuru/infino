<?php

declare(strict_types=1);

namespace App\Modules\Cart\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $variant_id
 * @property int $quantity
 * @property int $price_amount_minor
 * @property string $price_currency
 * @property int|null $compare_at_amount_minor
 * @property-read Cart $cart
 * @property-read ProductVariant $variant
 */
final class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'price_amount_minor' => 'integer',
        'price_currency' => 'string',
        'compare_at_amount_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function isValid(): bool
    {
        return $this->variant->is_active === true
            && $this->quantity >= 1
            && $this->quantity <= Cart::MAX_ITEM_QUANTITY;
    }

    public function getSubtotalMinor(): int
    {
        return $this->price_amount_minor * $this->quantity;
    }

    public function getSubtotalCurrency(): string
    {
        return $this->price_currency;
    }
}

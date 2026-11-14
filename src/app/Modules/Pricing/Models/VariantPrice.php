<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $currency
 * @property int $amount_minor
 * @property int|null $compare_at_amount_minor
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read ProductVariant $variant
 * @property-read HasMany<int, VariantPriceChange> $priceChanges
 */
final class VariantPrice extends Model
{
    /** @use HasFactory<VariantPriceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'currency' => 'string',
            'amount_minor' => 'integer',
            'compare_at_amount_minor' => 'integer',
        ];
    }

    protected static function newFactory(): VariantPriceFactory
    {
        return VariantPriceFactory::new();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return HasMany<int, VariantPriceChange>
     */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(VariantPriceChange::class, 'variant_id');
    }
}

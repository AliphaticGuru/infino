<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $currency
 * @property string $change_type
 * @property int|null $old_amount_minor
 * @property int|null $new_amount_minor
 * @property int|null $old_compare_at_amount_minor
 *                                                 property int|null $new_compare_at_amount_minor
 * @property CarbonInterface $created_at
 * @property-read ProductVariant $variant
 * @property-read User|null $changedBy
 */
final class VariantPriceChange extends Model
{
    /** @use HasFactory<VariantPriceChangeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'currency' => 'string',
            'change_type' => 'string',
            'old_amount_minor' => 'integer',
            'new_amount_minor' => 'integer',
            'old_compare_at_amount_minor' => 'integer',
            'new_compare_at_amount_minor' => 'integer',
        ];
    }

    protected static function newFactory(): VariantPriceChangeFactory
    {
        return VariantPriceChangeFactory::new();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

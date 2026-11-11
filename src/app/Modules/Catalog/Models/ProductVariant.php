<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property string|null $label
 * @property string|null $variant_key
 * @property bool $is_active
 * @property int $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Product $product
 */
final class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The default variant is the product's oldest variant.
     *
     * This keeps the invariant deterministic without a separate database flag:
     * product creation and backfill create the default variant first, and later
     * variants are appended.
     */
    public function isDefault(): bool
    {
        return $this->product->defaultVariant?->is($this) ?? false;
    }
}

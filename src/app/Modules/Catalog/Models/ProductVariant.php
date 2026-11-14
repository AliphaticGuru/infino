<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @property-read Collection<int, ProductVariantAttributeOption> $attributeOptions
 * @property-read Collection<int, ProductMedia> $media
 * @property-read ProductMedia|null $primaryMedia
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
     * @return HasMany<ProductVariantAttributeOption, $this>
     */
    public function attributeOptions(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeOption::class);
    }

    /**
     * The variant-level gallery images, ordered by position.
     *
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'product_variant_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * The variant primary image, or null when the variant gallery has no
     * primary.
     */
    public function primaryMedia(): ?ProductMedia
    {
        return $this->media()->where('is_primary', true)->first();
    }

    /**
     * The variant primary image, then the product primary, then the product's
     * first image, or null.
     */
    public function resolvedPrimaryMedia(): ?ProductMedia
    {
        return $this->primaryMedia()
            ?? $this->product->primaryMedia();
    }

    /**
     * The variant gallery when it has images, otherwise the product gallery.
     *
     * @return HasMany<ProductMedia, $this>|HasMany<ProductMedia, Product>
     */
    public function gallery(): HasMany
    {
        return $this->media()->exists()
            ? $this->media()
            : $this->product->media();
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

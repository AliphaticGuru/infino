<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductVariantAttributeOptionFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single option selected by a variant for one attribute dimension.
 *
 * The unique (product_variant_id, product_attribute_id) constraint and the
 * composite foreign key to product_attribute_options guarantee that a variant
 * holds at most one option per attribute and that the option belongs to that
 * attribute.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property int $product_attribute_id
 * @property int $product_attribute_option_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read ProductVariant $variant
 * @property-read ProductAttribute $attribute
 * @property-read ProductAttributeOption|null $option
 */
final class ProductVariantAttributeOption extends Model
{
    /** @use HasFactory<ProductVariantAttributeOptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'product_attribute_id' => 'integer',
            'product_attribute_option_id' => 'integer',
        ];
    }

    protected static function newFactory(): ProductVariantAttributeOptionFactory
    {
        return ProductVariantAttributeOptionFactory::new();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<ProductAttribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    /**
     * @return BelongsTo<ProductAttributeOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeOption::class, 'product_attribute_option_id');
    }
}

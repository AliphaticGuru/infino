<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductAttributeOptionFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An option owned by exactly one product attribute.
 *
 * @property int $id
 * @property int $product_attribute_id
 * @property string $code
 * @property string $label
 * @property int $position
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read ProductAttribute $attribute
 * @property-read Collection<int, ProductVariantAttributeOption> $variantSelections
 */
final class ProductAttributeOption extends Model
{
    /** @use HasFactory<ProductAttributeOptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_attribute_id' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProductAttributeOptionFactory
    {
        return ProductAttributeOptionFactory::new();
    }

    /**
     * @return BelongsTo<ProductAttribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    /**
     * @return HasMany<ProductVariantAttributeOption, $this>
     */
    public function variantSelections(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeOption::class, 'product_attribute_option_id');
    }
}

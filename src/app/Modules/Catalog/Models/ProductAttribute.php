<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductAttributeFactory;
use App\Modules\Catalog\Support\AttributeType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, global product attribute definition.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property AttributeType $type
 * @property bool $is_variant_dimension
 * @property int $position
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, ProductAttributeOption> $options
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, ProductVariantAttributeOption> $variantSelections
 */
final class ProductAttribute extends Model
{
    /** @use HasFactory<ProductAttributeFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'is_variant_dimension' => 'boolean',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProductAttributeFactory
    {
        return ProductAttributeFactory::new();
    }

    /**
     * @return HasMany<ProductAttributeOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductAttributeOption::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_product');
    }

    /**
     * @return HasMany<ProductVariantAttributeOption, $this>
     */
    public function variantSelections(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeOption::class);
    }
}

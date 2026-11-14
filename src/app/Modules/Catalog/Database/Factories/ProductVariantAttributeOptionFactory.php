<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\ProductVariantAttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariantAttributeOption>
 */
final class ProductVariantAttributeOptionFactory extends Factory
{
    protected $model = ProductVariantAttributeOption::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'product_attribute_id' => ProductAttribute::factory(),
            'product_attribute_option_id' => fn (array $attributes): int => ProductAttributeOption::factory()
                ->create(['product_attribute_id' => $attributes['product_attribute_id']])
                ->id,
        ];
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state([
            'product_variant_id' => $variant->id,
        ]);
    }

    /**
     * Bind the selection to an explicit option and its owning attribute.
     */
    public function forOption(ProductAttributeOption $option): static
    {
        return $this->state([
            'product_attribute_id' => $option->product_attribute_id,
            'product_attribute_option_id' => $option->id,
        ]);
    }
}

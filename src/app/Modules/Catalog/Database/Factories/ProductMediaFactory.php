<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductMedia;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMedia>
 */
final class ProductMediaFactory extends Factory
{
    protected $model = ProductMedia::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'disk' => 'public',
            'path' => 'catalog/products/'.fake()->uuid().'/'.fake()->uuid().'.jpg',
            'original_name' => fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'width' => fake()->numberBetween(100, 4000),
            'height' => fake()->numberBetween(100, 4000),
            'alt_text' => fake()->sentence(),
            'position' => 0,
            'is_primary' => false,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state([
            'product_id' => $product->id,
            'product_variant_id' => null,
        ]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function primary(): static
    {
        return $this->state([
            'is_primary' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_primary' => false,
        ]);
    }
}

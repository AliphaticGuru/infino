<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttributeOption>
 */
final class ProductAttributeOptionFactory extends Factory
{
    protected $model = ProductAttributeOption::class;

    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->unique()->words(2);
        $label = ucfirst(implode(' ', $words));

        return [
            'product_attribute_id' => ProductAttribute::factory(),
            'code' => Str::slug($label).'-'.fake()->unique()->numberBetween(1000, 9999),
            'label' => $label,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function forAttribute(ProductAttribute $attribute): static
    {
        return $this->state([
            'product_attribute_id' => $attribute->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Support\AttributeType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductAttribute>
 */
final class ProductAttributeFactory extends Factory
{
    protected $model = ProductAttribute::class;

    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->unique()->words(2);
        $name = ucfirst(implode(' ', $words));

        return [
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'type' => AttributeType::Select,
            'is_variant_dimension' => true,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function descriptive(): static
    {
        return $this->state([
            'is_variant_dimension' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}

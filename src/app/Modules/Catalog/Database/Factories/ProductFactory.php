<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [

            'name' => ucfirst($name),

            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),

            'description' => fake()->paragraph(),

            'is_active' => true,

            'status' => ProductStatus::Draft,
        ];
    }
<<<<<<< develop
}
=======

    public function published(): static
    {
        return $this->state([
            'status' => ProductStatus::Published,
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'status' => ProductStatus::Archived,
        ]);
    }
}
>>>>>>> local

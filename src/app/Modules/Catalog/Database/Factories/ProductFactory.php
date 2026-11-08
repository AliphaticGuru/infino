<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
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
        ];
    }
}
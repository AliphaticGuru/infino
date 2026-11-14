<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\ProductStatus;
use App\Modules\Catalog\Support\UniqueSku;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function configure(): static
    {
        return $this->has(
            ProductVariant::factory()->state(function (array $attributes, ?Model $parent): array {
                assert($parent instanceof Product);

                return [
                    'sku' => UniqueSku::generate('SKU-'.$parent->id),
                    'label' => 'Default',
                    'variant_key' => null,
                    'is_active' => $parent->is_active,
                    'position' => 0,
                ];
            }),
            'variants',
        );
    }

    public function withoutVariant(): static
    {
        return $this->newInstance([
            'has' => new Collection,
        ]);
    }

    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->words(3);
        $name = implode(' ', $words);

        return [

            'name' => ucfirst($name),

            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),

            'description' => fake()->paragraph(),

            'is_active' => true,

            'status' => ProductStatus::Draft,
        ];
    }

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

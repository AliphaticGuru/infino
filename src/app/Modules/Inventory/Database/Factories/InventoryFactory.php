<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
final class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'location' => 'default',
            'quantity' => fake()->numberBetween(0, 100),
        ];
    }

    public function forLocation(string $location): static
    {
        return $this->state([
            'location' => $location,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
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
            'variant_id' => ProductVariant::factory(),
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

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state([
            'variant_id' => $variant->id,
        ]);
    }
}

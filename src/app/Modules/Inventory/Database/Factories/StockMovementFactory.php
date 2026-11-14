<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
final class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $quantityDelta = fake()->numberBetween(1, 50) * fake()->randomElement([-1, 1]);

        return [
            'variant_id' => ProductVariant::factory(),
            'location' => 'default',
            'quantity_delta' => $quantityDelta,
            'resulting_quantity' => max(0, $quantityDelta),
            'reason' => fake()->sentence(3),
            'reference' => fake()->optional()->uuid(),
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

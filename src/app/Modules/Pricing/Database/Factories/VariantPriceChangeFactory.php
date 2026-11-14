<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Models\VariantPriceChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantPriceChange>
 */
final class VariantPriceChangeFactory extends Factory
{
    protected $model = VariantPriceChange::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'currency' => 'NGN',
            'change_type' => 'created',
            'old_amount_minor' => null,
            'new_amount_minor' => 0,
            'old_compare_at_amount_minor' => null,
            'new_compare_at_amount_minor' => null,
            'changed_by' => null,
        ];
    }

    public function ngn(): static
    {
        return $this->state([
            'currency' => 'NGN',
        ]);
    }

    public function created(): static
    {
        return $this->state([
            'change_type' => 'created',
            'old_amount_minor' => null,
        ]);
    }

    public function updated(): static
    {
        return $this->state([
            'change_type' => 'updated',
        ]);
    }

    public function removed(): static
    {
        return $this->state([
            'change_type' => 'removed',
            'old_amount_minor' => 1000,
            'new_amount_minor' => null,
        ]);
    }

    public function withChangedBy(int $userId): static
    {
        return $this->state([
            'changed_by' => $userId,
        ]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state([
            'variant_id' => $variant->id,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Pricing\Models\VariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VariantPrice>
 */
final class VariantPriceFactory extends Factory
{
    protected $model = VariantPrice::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'currency' => 'NGN',
            'amount_minor' => 0,
            'compare_at_amount_minor' => null,
        ];
    }

    public function ngn(): static
    {
        return $this->state([
            'currency' => 'NGN',
        ]);
    }

    public function usd(): static
    {
        return $this->state([
            'currency' => 'USD',
        ]);
    }

    public function eur(): static
    {
        return $this->state([
            'currency' => 'EUR',
        ]);
    }

    public function gbp(): static
    {
        return $this->state([
            'currency' => 'GBP',
        ]);
    }

    public function withAmount(int $amountMinor): static
    {
        return $this->state([
            'amount_minor' => $amountMinor,
        ]);
    }

    public function withCompareAt(int $compareAtMinor): static
    {
        return $this->state([
            'compare_at_amount_minor' => $compareAtMinor,
        ]);
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state([
            'variant_id' => $variant->id,
        ]);
    }
}

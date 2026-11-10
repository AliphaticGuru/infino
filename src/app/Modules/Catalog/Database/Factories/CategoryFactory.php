<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        /** @var array<int, string> $words */
        $words = fake()->unique()->words(2);
        $name = ucfirst(implode(' ', $words));

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->paragraph(),
            'is_active' => true,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
        ]);
    }
}

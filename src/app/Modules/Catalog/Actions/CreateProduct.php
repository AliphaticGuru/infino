<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreateProduct implements CreatesProducts
{
    public function handle(CreateProductData $data): Product
    {
        $validated = Validator::make(
            [
                'name' => $data->name,
                'description' => $data->description,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ],
        )->validate();

        return Product::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'product';
        $slug = $baseSlug;
        $suffix = 1;

        while (Product::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$baseSlug}-{$suffix}";
        }

        return $slug;
    }
}
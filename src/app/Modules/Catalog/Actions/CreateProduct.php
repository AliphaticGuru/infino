<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\UniqueSlug;
use Illuminate\Support\Facades\Validator;

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
            'slug' => UniqueSlug::generate(Product::class, $validated['name'], 'product'),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);
    }
}

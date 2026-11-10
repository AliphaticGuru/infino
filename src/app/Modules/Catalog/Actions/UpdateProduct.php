<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\UpdatesProducts;
use App\Modules\Catalog\Data\UpdateProductData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\UniqueSlug;
use Illuminate\Support\Facades\Validator;

final class UpdateProduct implements UpdatesProducts
{
    public function handle(Product $product, UpdateProductData $data): Product
    {
        $rules = [
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($data->name !== null) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        $validated = Validator::make(
            [
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => $data->isActive,
            ],
            $rules,
        )->validate();

        $attributes = [];

        if ($data->name !== null) {
            $attributes['name'] = $validated['name'];
            $attributes['slug'] = $product->name === $validated['name']
                ? $product->slug
                : UniqueSlug::generate(Product::class, $validated['name'], 'product', $product->id);
        }

        if ($data->description !== null) {
            $attributes['description'] = $validated['description'];
        }

        if ($data->isActive !== null) {
            $attributes['is_active'] = $validated['is_active'];
        }

        if ($attributes !== []) {
            $product->fill($attributes)->save();
        }

        return $product->refresh();
    }
}

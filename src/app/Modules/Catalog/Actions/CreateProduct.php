<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStatus;
use App\Modules\Catalog\Support\UniqueSku;
use App\Modules\Catalog\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CreateProduct implements CreatesProducts
{
    public function handle(CreateProductData $data): Product
    {
        $validated = Validator::make(
            [
                'name' => $data->name,
                'description' => $data->description,
                'sku' => $data->sku,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'sku' => ['nullable', 'string', 'max:100', 'unique:product_variants,sku'],
            ],
        )->validate();

        return DB::transaction(function () use ($validated): Product {
            $product = Product::create([
                'name' => $validated['name'],
                'slug' => UniqueSlug::generate(Product::class, $validated['name'], 'product'),
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'status' => ProductStatus::Draft,
            ]);

            $product->variants()->create([
                'sku' => $validated['sku'] ?? UniqueSku::generate('SKU-'.$product->id),
                'label' => 'Default',
                'variant_key' => null,
                'is_active' => true,
                'position' => 0,
            ]);

            return $product->refresh();
        });
    }
}

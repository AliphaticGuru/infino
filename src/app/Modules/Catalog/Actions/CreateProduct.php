<?php 
declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\CreatesProducts;
use App\Modules\Catalog\Data\CreateProductData;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Str;

final class CreateProduct implements CreatesProducts
{
    public function handle(CreateProductData $data): Product
    {
        return Product::create([

            'name' => $data->name,

            'slug' => Str::slug($data->name),

            'description' => $data->description,

            'is_active' => true,
        ]);
    }
}
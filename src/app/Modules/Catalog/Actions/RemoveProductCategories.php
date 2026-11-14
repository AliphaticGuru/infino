<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\RemovesProductCategories;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class RemoveProductCategories implements RemovesProductCategories
{
    /**
     * @param  array<int, int>  $categoryIds
     */
    public function handle(Product $product, array $categoryIds): Product
    {
        return DB::transaction(function () use ($product, $categoryIds): Product {
            $current = Product::query()
                ->whereKey($product->getKey())
                ->firstOrFail();

            $validated = Validator::make(
                ['category_ids' => array_values($categoryIds)],
                [
                    'category_ids' => ['array'],
                    'category_ids.*' => ['integer', 'distinct'],
                ],
            )->validate();

            $current->categories()->detach($validated['category_ids']);

            return $current->refresh();
        });
    }
}

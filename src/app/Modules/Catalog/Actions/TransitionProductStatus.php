<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\TransitionsProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionProductStatus implements TransitionsProductStatus
{
    public function publish(Product $product): Product
    {
        return $this->transition($product, ProductStatus::Published, [ProductStatus::Draft]);
    }

    public function unpublish(Product $product): Product
    {
        return $this->transition($product, ProductStatus::Draft, [ProductStatus::Published]);
    }

    public function archive(Product $product): Product
    {
        return $this->transition(
            $product,
            ProductStatus::Archived,
            [ProductStatus::Draft, ProductStatus::Published],
        );
    }

    public function restore(Product $product): Product
    {
        return $this->transition($product, ProductStatus::Draft, [ProductStatus::Archived]);
    }

    /**
     * @param  list<ProductStatus>  $allowedFrom
     */
    private function transition(Product $product, ProductStatus $target, array $allowedFrom): Product
    {
        return DB::transaction(function () use ($product, $target, $allowedFrom): Product {
            $current = Product::query()
                ->whereKey($product->getKey())
                ->firstOrFail();

            if (! in_array($current->status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'status' => "A product cannot change status from {$current->status->value} to {$target->value}.",
                ]);
            }

            $current->status = $target;
            $current->save();

            return $current->refresh();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Contracts\SetsPrimaryProductMedia;
use App\Modules\Catalog\Models\ProductMedia;
use Illuminate\Support\Facades\DB;

final class SetPrimaryProductMedia implements SetsPrimaryProductMedia
{
    public function handle(ProductMedia $media): ProductMedia
    {
        return DB::transaction(function () use ($media): ProductMedia {
            $current = ProductMedia::query()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $gallery = $current->product_variant_id === null
                ? $current->product->media()->lockForUpdate()->get()
                : $current->productVariant->media()->lockForUpdate()->get();

            foreach ($gallery as $existing) {
                if ($existing->is_primary && ! $existing->is($current)) {
                    $existing->update(['is_primary' => false]);
                }
            }

            if (! $current->is_primary) {
                $current->update(['is_primary' => true]);
            }

            return $current->fresh();
        });
    }
}

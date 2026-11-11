<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\ProductVariant;

final class UniqueSku
{
    public static function generate(string $prefix, ?int $ignoreId = null): string
    {
        $sku = $prefix;
        $suffix = 1;

        while (ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $suffix++;
            $sku = "{$prefix}-{$suffix}";
        }

        return $sku;
    }
}

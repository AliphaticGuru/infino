<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use Illuminate\Support\Facades\DB;

/**
 * Creates one default variant for every product that does not have one.
 *
 * This is intentionally query-builder based and idempotent so it can run from
 * the data migration and be exercised directly by tests.
 */
final class DefaultVariantBackfill
{
    public function handle(): int
    {
        $created = 0;

        DB::table('products')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('product_variants')
                    ->whereColumn('product_variants.product_id', 'products.id');
            })
            ->orderBy('products.id')
            ->chunkById(100, function ($products) use (&$created): void {
                foreach ($products as $product) {
                    DB::table('product_variants')->insert([
                        'product_id' => $product->id,
                        'sku' => UniqueSku::generate('SKU-'.$product->id),
                        'label' => 'Default',
                        'variant_key' => null,
                        'is_active' => (bool) $product->is_active,
                        'position' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created++;
                }
            });

        return $created;
    }
}

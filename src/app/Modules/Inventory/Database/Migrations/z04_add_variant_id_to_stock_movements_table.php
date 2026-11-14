<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('variant_id')
                ->nullable()
                ->after('id')
                ->index();
        });

        DB::table('stock_movements')->update([
            'variant_id' => DB::raw(
                '(SELECT product_variants.id FROM product_variants WHERE product_variants.product_id = stock_movements.product_id ORDER BY product_variants.position ASC, product_variants.id ASC LIMIT 1)'
            ),
        ]);

        $missing = DB::table('stock_movements')->whereNull('variant_id')->count();

        if ($missing > 0) {
            throw new RuntimeException(
                "Unable to map {$missing} stock movement record(s) to a product variant."
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('stock_movements', 'variant_id')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('variant_id');
        });
    }
};

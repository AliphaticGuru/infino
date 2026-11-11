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
     *
     * The table is rebuilt so the final variant foreign key is enforced on both
     * PostgreSQL and SQLite. Existing movement history is preserved.
     */
    public function up(): void
    {
        Schema::create('stock_movements_new', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('variant_id');
            $table->foreign('variant_id', 'stock_movements_variant_id_foreign')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->string('location', 100);

            $table->integer('quantity_delta');

            $table->integer('resulting_quantity');

            $table->string('reason');

            $table->string('reference')
                ->nullable();

            $table->timestamp('created_at')
                ->nullable();

            $table->index(
                ['variant_id', 'location'],
                'stock_movements_variant_id_location_index',
            );
            $table->index(
                ['variant_id', 'created_at'],
                'stock_movements_variant_id_created_at_index',
            );
        });

        DB::table('stock_movements_new')->insertUsing(
            [
                'id',
                'variant_id',
                'location',
                'quantity_delta',
                'resulting_quantity',
                'reason',
                'reference',
                'created_at',
            ],
            DB::table('stock_movements')->select([
                'id',
                'variant_id',
                'location',
                'quantity_delta',
                'resulting_quantity',
                'reason',
                'reference',
                'created_at',
            ]),
        );

        Schema::drop('stock_movements');
        Schema::rename('stock_movements_new', 'stock_movements');

        $this->resetSequence();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('stock_movements_legacy', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id');
            $table->foreign('product_id', 'stock_movements_product_id_foreign')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->string('location', 100);

            $table->integer('quantity_delta');

            $table->integer('resulting_quantity');

            $table->string('reason');

            $table->string('reference')
                ->nullable();

            $table->timestamp('created_at')
                ->nullable();

            $table->index(
                ['product_id', 'location'],
                'stock_movements_product_id_location_index',
            );
            $table->index(
                ['product_id', 'created_at'],
                'stock_movements_product_id_created_at_index',
            );
        });

        DB::table('stock_movements_legacy')->insertUsing(
            [
                'id',
                'product_id',
                'location',
                'quantity_delta',
                'resulting_quantity',
                'reason',
                'reference',
                'created_at',
            ],
            DB::table('stock_movements')
                ->join('product_variants', 'product_variants.id', '=', 'stock_movements.variant_id')
                ->select([
                    'stock_movements.id',
                    'product_variants.product_id',
                    'stock_movements.location',
                    'stock_movements.quantity_delta',
                    'stock_movements.resulting_quantity',
                    'stock_movements.reason',
                    'stock_movements.reference',
                    'stock_movements.created_at',
                ]),
        );

        Schema::drop('stock_movements');
        Schema::rename('stock_movements_legacy', 'stock_movements');

        $this->resetSequence();
    }

    private function resetSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('stock_movements', 'id'), COALESCE((SELECT MAX(id) FROM stock_movements), 1), true)"
        );
    }
};

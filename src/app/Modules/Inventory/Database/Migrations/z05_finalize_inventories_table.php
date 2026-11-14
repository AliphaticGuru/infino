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
     * PostgreSQL and SQLite. Existing inventory IDs and quantities are preserved.
     */
    public function up(): void
    {
        Schema::create('inventories_new', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('variant_id');
            $table->foreign('variant_id', 'inventories_variant_id_foreign')
                ->references('id')
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->string('location', 100)
                ->default('default');

            $table->integer('quantity')
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['variant_id', 'location'],
                'inventories_variant_id_location_unique',
            );
        });

        DB::table('inventories_new')->insertUsing(
            ['id', 'variant_id', 'location', 'quantity', 'created_at', 'updated_at'],
            DB::table('inventories')->select([
                'id',
                'variant_id',
                'location',
                'quantity',
                'created_at',
                'updated_at',
            ]),
        );

        Schema::drop('inventories');
        Schema::rename('inventories_new', 'inventories');

        $this->resetSequence();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('inventories_legacy', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id');
            $table->foreign('product_id', 'inventories_product_id_foreign')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->string('location', 100)
                ->default('default');

            $table->integer('quantity')
                ->default(0);

            $table->timestamps();

            $table->unique(
                ['product_id', 'location'],
                'inventories_product_id_location_unique',
            );
        });

        DB::table('inventories_legacy')->insertUsing(
            ['id', 'product_id', 'location', 'quantity', 'created_at', 'updated_at'],
            DB::table('inventories')
                ->join('product_variants', 'product_variants.id', '=', 'inventories.variant_id')
                ->select([
                    'inventories.id',
                    'product_variants.product_id',
                    'inventories.location',
                    'inventories.quantity',
                    'inventories.created_at',
                    'inventories.updated_at',
                ]),
        );

        Schema::drop('inventories');
        Schema::rename('inventories_legacy', 'inventories');

        $this->resetSequence();
    }

    private function resetSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT setval(pg_get_serial_sequence('inventories', 'id'), COALESCE((SELECT MAX(id) FROM inventories), 1), true)"
        );
    }
};

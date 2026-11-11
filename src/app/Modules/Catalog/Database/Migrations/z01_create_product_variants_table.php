<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The filename sorts after the existing create_* migrations and before
     * z_add_status_to_products_table. Product variants only depend on the
     * products table, so this ordering is safe on fresh PostgreSQL databases.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('sku', 100)
                ->nullable()
                ->unique();

            $table->string('label')
                ->nullable();

            $table->string('variant_key')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->integer('position')
                ->default(0);

            $table->timestamps();

            $table->unique(['product_id', 'variant_key']);
            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

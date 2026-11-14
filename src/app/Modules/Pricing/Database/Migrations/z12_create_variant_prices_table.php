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
     * Current sellable price per variant and currency.
     * One row per (variant_id, currency) with unique constraint.
     */
    public function up(): void
    {
        Schema::create('variant_prices', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('variant_id');

            $table->string('currency', 3)->default('NGN');

            $table->unsignedBigInteger('amount_minor');

            $table->unsignedBigInteger('compare_at_amount_minor')->nullable();

            $table->timestamps();

            // Ensure variant exists and cascade delete
            $table->foreign('variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');

            // Unique constraint: one current price per variant and currency
            $table->unique(['variant_id', 'currency'], 'variant_prices_variant_id_currency_unique');

            // Index for currency-scoped reads
            $table->index(['currency', 'variant_id'], 'variant_prices_currency_variant_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
    }
};

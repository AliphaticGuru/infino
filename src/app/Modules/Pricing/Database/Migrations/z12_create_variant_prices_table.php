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
     * Current sellable price per variant and currency.
     * One row per (variant_id, currency) with unique constraint.
     *
     * PostgreSQL CHECK constraint (enforced at DB level):
     *   compare_at_amount_minor >= amount_minor when compare-at is present.
     *   when compare_at_amount_minor IS NULL, the constraint is trivially satisfied.
     *
     * Application-level validation (SetVariantPrice action) also enforces:
     *   compare-at price must be strictly greater than the base price.
     *
     * NOTE: SQLite does not support this CHECK constraint through the schema
     * builder; the application layer (SetVariantPrice) enforces the invariant
     * there and on every driver.
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

        // PostgreSQL enforces the compare-at invariant at the database level.
        // SQLite falls back to the application-level validation in
        // SetVariantPrice, which applies on every driver.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE variant_prices ADD CONSTRAINT variant_prices_compare_at_gte_amount '.
                'CHECK (compare_at_amount_minor IS NULL OR compare_at_amount_minor >= amount_minor)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
    }
};

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
     * Append-only ledger for price changes and removals.
     * No updated_at column: rows are never updated.
     */
    public function up(): void
    {
        Schema::create('variant_price_changes', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('variant_id');

            $table->string('currency', 3)->default('NGN');

            $table->string('change_type', 20);

            $table->unsignedBigInteger('old_amount_minor')->nullable();

            $table->unsignedBigInteger('new_amount_minor')->nullable();

            $table->unsignedBigInteger('old_compare_at_amount_minor')->nullable();

            $table->unsignedBigInteger('new_compare_at_amount_minor')->nullable();

            $table->unsignedBigInteger('changed_by')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // No updated_at: rows are append-only

            // Ensure variant exists
            $table->foreign('variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');

            // Ensure changed_by references users table if set
            $table->foreign('changed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for common query patterns
            $table->index(['variant_id', 'created_at'], 'variant_price_changes_variant_id_created_at_index');

            $table->index(['currency', 'created_at'], 'variant_price_changes_currency_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_price_changes');
    }
};

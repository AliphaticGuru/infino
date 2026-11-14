<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('cart_id');

            $table->unsignedBigInteger('variant_id');

            $table->integer('quantity')->default(1);

            // Price snapshots at add-time — must not be recalculated later
            $table->integer('price_amount_minor');

            $table->string('price_currency', 3)->default('NGN');

            $table->integer('compare_at_amount_minor')->nullable();

            $table->timestamps();

            // Ensure cart exists
            $table->foreign('cart_id')
                ->references('id')
                ->on('carts')
                ->onDelete('cascade');

            // Ensure variant exists
            $table->foreign('variant_id')
                ->references('id')
                ->on('product_variants')
                ->onDelete('cascade');

            // One cart item per (cart_id, variant_id) — prevent duplicates
            $table->unique(['cart_id', 'variant_id'], 'cart_items_cart_id_variant_id_unique');

            // Indexes for common queries
            $table->index(['cart_id']);

            $table->index(['variant_id']);

            $table->index(['cart_id', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

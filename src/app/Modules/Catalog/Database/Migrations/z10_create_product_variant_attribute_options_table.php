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
     * A variant selects at most one option per attribute. The composite foreign
     * key references the (id, product_attribute_id) unique constraint on
     * product_attribute_options, so an option can never be attached to a
     * different attribute even if application validation is bypassed.
     */
    public function up(): void
    {
        Schema::create('product_variant_attribute_options', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes')
                ->restrictOnDelete();

            $table->unsignedBigInteger('product_attribute_option_id');

            $table->timestamps();

            $table->unique(['product_variant_id', 'product_attribute_id']);
            $table->index('product_attribute_id');
            $table->index('product_attribute_option_id');

            $table->foreign(['product_attribute_option_id', 'product_attribute_id'])
                ->references(['id', 'product_attribute_id'])
                ->on('product_attribute_options')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_options');
    }
};

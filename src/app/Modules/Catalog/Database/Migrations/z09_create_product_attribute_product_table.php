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
     * A product may be assigned the attributes that describe its variant
     * dimensions. The composite primary key makes each assignment unique.
     */
    public function up(): void
    {
        Schema::create('product_attribute_product', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes')
                ->cascadeOnDelete();

            $table->primary(['product_id', 'product_attribute_id']);
            $table->index('product_attribute_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_product');
    }
};

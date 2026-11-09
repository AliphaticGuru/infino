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
     * The filename sorts after the existing untimestamped product migration so
     * the product foreign key is available when this pivot table is created.
     */
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->primary(['category_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};

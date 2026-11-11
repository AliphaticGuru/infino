<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table): void {
            $table->id();

            // The product foreign key is intentionally deferred. This migration
            // sorts before create_products_table, so an inline constraint would
            // fail on a fresh PostgreSQL database. The variant re-key migration
            // replaces this column with variant_id and adds the final foreign key.
            $table->foreignId('product_id');

            $table->string('location', 100)
                ->default('default');

            $table->integer('quantity')
                ->default(0);

            $table->timestamps();

            $table->unique(['product_id', 'location']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};

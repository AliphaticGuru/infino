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
     * The z07-z10 range follows the Inventory z03-z06 migrations and the
     * Catalog z01-z02 variant migrations. Product attributes only depend on
     * the products table, so this ordering is safe on fresh databases.
     */
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->id();

            $table->string('code', 100)->unique();

            $table->string('name');

            $table->string('type', 20)->default('select');

            $table->boolean('is_variant_dimension')->default(false);

            $table->integer('position')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};

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
     * Options are owned by one attribute. The (id, product_attribute_id)
     * unique constraint is the target of the composite foreign key on
     * product_variant_attribute_options, which prevents attaching an option to
     * a different attribute at the database level.
     */
    public function up(): void
    {
        Schema::create('product_attribute_options', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_attribute_id')
                ->constrained('product_attributes')
                ->cascadeOnDelete();

            $table->string('code', 100);

            $table->string('label');

            $table->integer('position')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['product_attribute_id', 'code']);
            $table->unique(['id', 'product_attribute_id']);
            $table->index(['product_attribute_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_options');
    }
};

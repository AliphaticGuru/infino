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
     * Product media belongs to Catalog. Each row is owned by a product and
     * optionally by one of its variants. The composite foreign key
     * (product_variant_id, product_id) guarantees a variant image cannot belong
     * to another product, backed by an additive unique index on
     * product_variants (id, product_id).
     *
     * PostgreSQL-only partial unique indexes enforce one primary image per
     * product gallery and one primary per variant gallery.
     */
    public function up(): void
    {
        // Additive unique index so the composite FK can target product_variants.
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unique(['id', 'product_id'], 'product_variants_id_product_id_unique');
        });

        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('product_variant_id')->nullable();

            $table->string('disk', 50)->default('public');

            $table->string('path', 1024);

            $table->string('original_name', 255)->nullable();

            $table->string('mime_type', 100)->nullable();

            $table->unsignedBigInteger('size')->nullable();

            $table->unsignedInteger('width')->nullable();

            $table->unsignedInteger('height')->nullable();

            $table->string('alt_text', 255)->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // A variant image must reference a variant of the same product.
            $table->foreign(['product_variant_id', 'product_id'])
                ->references(['id', 'product_id'])
                ->on('product_variants')
                ->cascadeOnDelete();

            $table->index(['product_id', 'position']);
            $table->index(['product_variant_id', 'position']);
            $table->index(['product_id', 'is_primary']);
        });

        // PostgreSQL partial unique indexes: one primary per gallery. SQLite
        // cannot express partial indexes through the schema builder, so the
        // application layer enforces the same invariant there.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX product_media_product_primary_unique '.
                'ON product_media (product_id) '.
                'WHERE is_primary AND product_variant_id IS NULL'
            );

            DB::statement(
                'CREATE UNIQUE INDEX product_media_variant_primary_unique '.
                'ON product_media (product_variant_id) '.
                'WHERE is_primary AND product_variant_id IS NOT NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_media');

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('product_variants_id_product_id_unique');
        });
    }
};

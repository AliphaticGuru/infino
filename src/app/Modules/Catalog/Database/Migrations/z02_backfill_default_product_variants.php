<?php

declare(strict_types=1);

use App\Modules\Catalog\Support\DefaultVariantBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(DefaultVariantBackfill::class)->handle();
    }

    /**
     * Reverse the migrations.
     *
     * The variants table is dropped by the previous migration, so this data
     * migration has nothing to reverse independently.
     */
    public function down(): void
    {
        //
    }
};

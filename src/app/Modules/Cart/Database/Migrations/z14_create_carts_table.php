<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('token')->unique()->nullable();

            $table->string('status')->default('active');

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['user_id', 'status']);

            $table->index(['token', 'status']);

            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

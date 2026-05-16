<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tryon_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('product_id')->nullable()->index();
            $table->string('product_type')->nullable();
            $table->text('original_user_image_path')->nullable();
            $table->text('product_image_path');
            $table->text('generated_image_path')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tryon_generations');
    }
};

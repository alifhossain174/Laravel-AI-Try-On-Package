<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tryon_usages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'generated_at']);
            $table->index(['ip_address', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tryon_usages');
    }
};

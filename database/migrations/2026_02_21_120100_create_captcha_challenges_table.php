<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captcha_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('target_key', 50);
            $table->json('option_image_ids');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('target_key');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_challenges');
    }
};

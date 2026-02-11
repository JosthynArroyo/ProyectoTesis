<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('captcha_images', function (Blueprint $table) {
            $table->id();
            $table->string('class_key', 50);
            $table->string('dataset_split', 20)->default('val');
            $table->string('image_path', 500)->unique();
            $table->timestamps();

            $table->index('class_key');
            $table->index(['class_key', 'dataset_split']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('captcha_images');
    }
};

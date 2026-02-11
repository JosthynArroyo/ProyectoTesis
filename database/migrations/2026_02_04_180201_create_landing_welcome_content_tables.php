<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_welcome_info_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80)->nullable();
            $table->string('value', 40)->nullable();
            $table->string('description', 160)->nullable();
            $table->string('icon', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('landing_welcome_doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('specialty', 80)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('landing_welcome_prices', function (Blueprint $table) {
            $table->id();
            $table->string('service', 80);
            $table->string('price', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('landing_welcome_featured_specialties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('especialidad_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('especialidad_id')
                ->references('id')
                ->on('especialidades')
                ->onDelete('cascade');
            $table->unique('especialidad_id', 'landing_welcome_featured_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_welcome_featured_specialties');
        Schema::dropIfExists('landing_welcome_prices');
        Schema::dropIfExists('landing_welcome_doctors');
        Schema::dropIfExists('landing_welcome_info_cards');
    }
};

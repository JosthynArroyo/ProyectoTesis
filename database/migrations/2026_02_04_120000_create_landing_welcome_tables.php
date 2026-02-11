<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_welcome_settings', function (Blueprint $table) {
            $table->id();
            $table->string('header_logo')->nullable();
            $table->string('header_name', 80)->nullable();
            $table->string('header_menu_home', 40)->nullable();
            $table->string('header_menu_services', 40)->nullable();
            $table->string('header_menu_contact', 40)->nullable();
            $table->string('header_login_text', 40)->nullable();
            $table->boolean('header_show_socials')->default(true);
            $table->string('hero_badge', 120)->nullable();
            $table->string('hero_title', 180)->nullable();
            $table->string('hero_subtitle', 240)->nullable();
            $table->string('hero_primary_text', 60)->nullable();
            $table->string('hero_primary_link', 180)->nullable();
            $table->string('hero_secondary_text', 60)->nullable();
            $table->string('hero_secondary_link', 180)->nullable();
            $table->timestamps();
        });

        Schema::create('landing_welcome_stats', function (Blueprint $table) {
            $table->id();
            $table->string('label', 60)->nullable();
            $table->string('value', 30)->nullable();
            $table->string('note', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('landing_welcome_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_welcome_slides');
        Schema::dropIfExists('landing_welcome_stats');
        Schema::dropIfExists('landing_welcome_settings');
    }
};

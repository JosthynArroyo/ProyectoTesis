<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_welcome_slides', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_welcome_slides', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('image_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_welcome_slides', function (Blueprint $table) {
            if (Schema::hasColumn('landing_welcome_slides', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};

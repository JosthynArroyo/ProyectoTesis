<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_welcome_settings', 'show_services_block')) {
                $table->boolean('show_services_block')->default(true)->after('hero_secondary_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (Schema::hasColumn('landing_welcome_settings', 'show_services_block')) {
                $table->dropColumn('show_services_block');
            }
        });
    }
};

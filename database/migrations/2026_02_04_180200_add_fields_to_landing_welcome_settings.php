<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('landing_welcome_settings', 'hero_show_primary')) {
                $table->boolean('hero_show_primary')->default(true)->after('hero_primary_text');
            }
            if (!Schema::hasColumn('landing_welcome_settings', 'hero_show_secondary')) {
                $table->boolean('hero_show_secondary')->default(true)->after('hero_secondary_text');
            }
            if (!Schema::hasColumn('landing_welcome_settings', 'prices_subtitle')) {
                $table->string('prices_subtitle', 240)->nullable()->after('hero_secondary_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (Schema::hasColumn('landing_welcome_settings', 'prices_subtitle')) {
                $table->dropColumn('prices_subtitle');
            }
            if (Schema::hasColumn('landing_welcome_settings', 'hero_show_secondary')) {
                $table->dropColumn('hero_show_secondary');
            }
            if (Schema::hasColumn('landing_welcome_settings', 'hero_show_primary')) {
                $table->dropColumn('hero_show_primary');
            }
        });
    }
};

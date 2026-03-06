<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('landing_welcome_settings', 'hero_followup_title')) {
                $table->string('hero_followup_title', 120)->nullable()->after('hero_subtitle');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'hero_followup_subtitle')) {
                $table->string('hero_followup_subtitle', 240)->nullable()->after('hero_followup_title');
            }

            if (! Schema::hasColumn('landing_welcome_settings', 'intro_badge')) {
                $table->string('intro_badge', 60)->nullable()->after('show_services_block');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_title')) {
                $table->string('intro_title', 180)->nullable()->after('intro_badge');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_subtitle')) {
                $table->string('intro_subtitle', 240)->nullable()->after('intro_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_1_title')) {
                $table->string('intro_feature_1_title', 120)->nullable()->after('intro_subtitle');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_1_text')) {
                $table->string('intro_feature_1_text', 240)->nullable()->after('intro_feature_1_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_2_title')) {
                $table->string('intro_feature_2_title', 120)->nullable()->after('intro_feature_1_text');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_2_text')) {
                $table->string('intro_feature_2_text', 240)->nullable()->after('intro_feature_2_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_3_title')) {
                $table->string('intro_feature_3_title', 120)->nullable()->after('intro_feature_2_text');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_3_text')) {
                $table->string('intro_feature_3_text', 240)->nullable()->after('intro_feature_3_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_4_title')) {
                $table->string('intro_feature_4_title', 120)->nullable()->after('intro_feature_3_text');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'intro_feature_4_text')) {
                $table->string('intro_feature_4_text', 240)->nullable()->after('intro_feature_4_title');
            }

            if (! Schema::hasColumn('landing_welcome_settings', 'services_badge')) {
                $table->string('services_badge', 60)->nullable()->after('intro_feature_4_text');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'services_title')) {
                $table->string('services_title', 160)->nullable()->after('services_badge');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'services_subtitle')) {
                $table->string('services_subtitle', 240)->nullable()->after('services_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'services_button_text')) {
                $table->string('services_button_text', 60)->nullable()->after('services_subtitle');
            }

            if (! Schema::hasColumn('landing_welcome_settings', 'prices_badge')) {
                $table->string('prices_badge', 60)->nullable()->after('prices_subtitle');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_title')) {
                $table->string('prices_title', 160)->nullable()->after('prices_badge');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_button_text')) {
                $table->string('prices_button_text', 60)->nullable()->after('prices_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_highlight_title')) {
                $table->string('prices_highlight_title', 120)->nullable()->after('prices_button_text');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_highlight_subtitle')) {
                $table->string('prices_highlight_subtitle', 240)->nullable()->after('prices_highlight_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_highlight_image')) {
                $table->string('prices_highlight_image', 180)->nullable()->after('prices_highlight_subtitle');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_visit_title')) {
                $table->string('prices_visit_title', 120)->nullable()->after('prices_highlight_image');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'prices_visit_subtitle')) {
                $table->string('prices_visit_subtitle', 240)->nullable()->after('prices_visit_title');
            }

            if (! Schema::hasColumn('landing_welcome_settings', 'doctors_badge')) {
                $table->string('doctors_badge', 60)->nullable()->after('prices_visit_subtitle');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'doctors_title')) {
                $table->string('doctors_title', 160)->nullable()->after('doctors_badge');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'doctors_subtitle')) {
                $table->string('doctors_subtitle', 240)->nullable()->after('doctors_title');
            }
            if (! Schema::hasColumn('landing_welcome_settings', 'doctors_pill')) {
                $table->string('doctors_pill', 100)->nullable()->after('doctors_subtitle');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            $columns = [
                'hero_followup_title',
                'hero_followup_subtitle',
                'intro_badge',
                'intro_title',
                'intro_subtitle',
                'intro_feature_1_title',
                'intro_feature_1_text',
                'intro_feature_2_title',
                'intro_feature_2_text',
                'intro_feature_3_title',
                'intro_feature_3_text',
                'intro_feature_4_title',
                'intro_feature_4_text',
                'services_badge',
                'services_title',
                'services_subtitle',
                'services_button_text',
                'prices_badge',
                'prices_title',
                'prices_button_text',
                'prices_highlight_title',
                'prices_highlight_subtitle',
                'prices_highlight_image',
                'prices_visit_title',
                'prices_visit_subtitle',
                'doctors_badge',
                'doctors_title',
                'doctors_subtitle',
                'doctors_pill',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('landing_welcome_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

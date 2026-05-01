<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_welcome_slides', function (Blueprint $table) {
            if (! Schema::hasColumn('landing_welcome_slides', 'alt')) {
                $table->string('alt', 120)->nullable()->after('image_path');
            }
            if (! Schema::hasColumn('landing_welcome_slides', 'title')) {
                $table->string('title', 120)->nullable()->after('alt');
            }
            if (! Schema::hasColumn('landing_welcome_slides', 'text')) {
                $table->string('text', 240)->nullable()->after('title');
            }
        });

        Schema::table('landing_welcome_doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('landing_welcome_doctors', 'experience_label')) {
                $table->string('experience_label', 120)->nullable()->after('photo_path');
            }
            if (! Schema::hasColumn('landing_welcome_doctors', 'featured_label')) {
                $table->string('featured_label', 60)->nullable()->after('experience_label');
            }
            if (! Schema::hasColumn('landing_welcome_doctors', 'attendance_label')) {
                $table->string('attendance_label', 80)->nullable()->after('featured_label');
            }
            if (! Schema::hasColumn('landing_welcome_doctors', 'availability_label')) {
                $table->string('availability_label', 80)->nullable()->after('attendance_label');
            }
            if (! Schema::hasColumn('landing_welcome_doctors', 'cta_text')) {
                $table->string('cta_text', 60)->nullable()->after('availability_label');
            }
            if (! Schema::hasColumn('landing_welcome_doctors', 'pill_text')) {
                $table->string('pill_text', 100)->nullable()->after('cta_text');
            }
        });

        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('landing_welcome_settings', 'header_login_text')) {
                $table->string('header_login_text', 40)->nullable()->after('header_show_socials');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_welcome_settings', function (Blueprint $table) {
            if (Schema::hasColumn('landing_welcome_settings', 'header_login_text')) {
                $table->dropColumn('header_login_text');
            }
        });

        Schema::table('landing_welcome_doctors', function (Blueprint $table) {
            $columns = [
                'experience_label',
                'featured_label',
                'attendance_label',
                'availability_label',
                'cta_text',
                'pill_text',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('landing_welcome_doctors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('landing_welcome_slides', function (Blueprint $table) {
            $columns = ['alt', 'title', 'text'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('landing_welcome_slides', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

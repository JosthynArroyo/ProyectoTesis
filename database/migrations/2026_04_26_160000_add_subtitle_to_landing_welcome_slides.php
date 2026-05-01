<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_welcome_slides', function (Blueprint $table) {
            if (! Schema::hasColumn('landing_welcome_slides', 'subtitle')) {
                $table->string('subtitle', 120)->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('landing_welcome_slides', function (Blueprint $table) {
            if (Schema::hasColumn('landing_welcome_slides', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('face_profiles', 'descriptors')) {
                $table->json('descriptors')->nullable()->after('descriptor');
            }
            if (! Schema::hasColumn('face_profiles', 'last_enrolled_at')) {
                $table->timestamp('last_enrolled_at')->nullable()->after('last_verified_at');
            }
            if (! Schema::hasColumn('face_profiles', 'last_enroll_ip')) {
                $table->string('last_enroll_ip', 45)->nullable()->after('last_enrolled_at');
            }
            if (! Schema::hasColumn('face_profiles', 'last_enroll_user_agent')) {
                $table->string('last_enroll_user_agent', 255)->nullable()->after('last_enroll_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('face_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('face_profiles', 'last_enroll_user_agent')) {
                $table->dropColumn('last_enroll_user_agent');
            }
            if (Schema::hasColumn('face_profiles', 'last_enroll_ip')) {
                $table->dropColumn('last_enroll_ip');
            }
            if (Schema::hasColumn('face_profiles', 'last_enrolled_at')) {
                $table->dropColumn('last_enrolled_at');
            }
            if (Schema::hasColumn('face_profiles', 'descriptors')) {
                $table->dropColumn('descriptors');
            }
        });
    }
};

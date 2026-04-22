<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (! Schema::hasColumn('citas_medicas', 'pending_since')) {
                $table->timestamp('pending_since')->nullable()->after('hora');
            }
            if (! Schema::hasColumn('citas_medicas', 'priority_score')) {
                $table->integer('priority_score')->default(0)->after('pending_since');
            }
            if (! Schema::hasColumn('citas_medicas', 'priority_level')) {
                $table->string('priority_level', 20)->default('baja')->after('priority_score');
            }
            if (! Schema::hasColumn('citas_medicas', 'last_priority_notified_at')) {
                $table->timestamp('last_priority_notified_at')->nullable()->after('priority_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (Schema::hasColumn('citas_medicas', 'last_priority_notified_at')) {
                $table->dropColumn('last_priority_notified_at');
            }
            if (Schema::hasColumn('citas_medicas', 'priority_level')) {
                $table->dropColumn('priority_level');
            }
            if (Schema::hasColumn('citas_medicas', 'priority_score')) {
                $table->dropColumn('priority_score');
            }
            if (Schema::hasColumn('citas_medicas', 'pending_since')) {
                $table->dropColumn('pending_since');
            }
        });
    }
};

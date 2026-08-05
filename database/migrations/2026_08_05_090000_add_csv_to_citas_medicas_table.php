<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('citas_medicas')) {
            Schema::table('citas_medicas', function (Blueprint $table) {
                if (! Schema::hasColumn('citas_medicas', 'csv')) {
                    $table->string('csv', 20)->nullable()->unique()->after('token_validacion');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('citas_medicas')) {
            Schema::table('citas_medicas', function (Blueprint $table) {
                if (Schema::hasColumn('citas_medicas', 'csv')) {
                    $table->dropColumn('csv');
                }
            });
        }
    }
};

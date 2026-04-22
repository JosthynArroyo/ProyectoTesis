<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('especialidades', function (Blueprint $table) {
            if (! Schema::hasColumn('especialidades', 'icono')) {
                $table->string('icono', 80)->nullable()->after('descripcion');
            }
            if (! Schema::hasColumn('especialidades', 'activo')) {
                $table->boolean('activo')->default(true)->after('icono');
            }
            if (! Schema::hasColumn('especialidades', 'orden')) {
                $table->unsignedInteger('orden')->default(0)->after('activo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('especialidades', function (Blueprint $table) {
            if (Schema::hasColumn('especialidades', 'orden')) {
                $table->dropColumn('orden');
            }
            if (Schema::hasColumn('especialidades', 'activo')) {
                $table->dropColumn('activo');
            }
            if (Schema::hasColumn('especialidades', 'icono')) {
                $table->dropColumn('icono');
            }
        });
    }
};

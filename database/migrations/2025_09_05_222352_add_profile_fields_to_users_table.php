<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono', 50)->nullable()->after('email');
            $table->string('dni', 50)->nullable()->after('telefono');
            $table->string('direccion')->nullable()->after('dni');
            $table->date('fecha_nacimiento')->nullable()->after('direccion');
            $table->string('sexo', 15)->nullable()->after('fecha_nacimiento');
            $table->string('avatar')->nullable()->after('sexo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono','dni','direccion','fecha_nacimiento','sexo','avatar']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Precio por consulta del doctor (nullable para usuarios que no son doctor)
            $table->decimal('precio_consulta', 10, 2)->nullable()->after('avatar');
            // Moneda fija (Ecuador -> USD)
            $table->string('moneda', 3)->default('USD')->after('precio_consulta');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['precio_consulta', 'moneda']);
        });
    }
};

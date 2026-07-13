<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas', function (Blueprint $table): void {
            $table->string('csv', 32)->nullable()->unique()->after('pdf_path');
        });

        Schema::table('certificados_medicos', function (Blueprint $table): void {
            $table->string('csv', 32)->nullable()->unique()->after('codigo');
        });

        Schema::table('pedidos_laboratorio', function (Blueprint $table): void {
            $table->string('csv', 32)->nullable()->unique()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('recetas', function (Blueprint $table): void {
            $table->dropUnique(['csv']);
            $table->dropColumn('csv');
        });

        Schema::table('certificados_medicos', function (Blueprint $table): void {
            $table->dropUnique(['csv']);
            $table->dropColumn('csv');
        });

        Schema::table('pedidos_laboratorio', function (Blueprint $table): void {
            $table->dropUnique(['csv']);
            $table->dropColumn('csv');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->string('tipo', 30)->default('rutina');
            $table->string('categoria', 80)->default('General');
            $table->boolean('requiere_orden')->default(false);
            $table->text('indicaciones_default')->nullable();
            $table->unsignedSmallInteger('ayuno_horas')->nullable();
            $table->text('restricciones')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            $table->dropColumn([
                'tipo',
                'categoria',
                'requiere_orden',
                'indicaciones_default',
                'ayuno_horas',
                'restricciones',
            ]);
        });
    }
};

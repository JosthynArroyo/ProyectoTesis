<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'confirmada', 'cancelada', 'realizada', 'no_se_presento'])
                ->default('pendiente')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'confirmada', 'cancelada', 'realizada'])
                ->default('pendiente')
                ->change();
        });
    }
};

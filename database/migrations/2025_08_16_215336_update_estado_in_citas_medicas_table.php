<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEstadoInCitasMedicasTable extends Migration
{
    public function up()
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'confirmada', 'cancelada', 'realizada'])
                  ->default('pendiente')
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'cancelada', 'reagendada'])
                  ->default('pendiente')
                  ->change();
        });
    }
}

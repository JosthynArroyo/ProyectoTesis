<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cita_eventos', function (Blueprint $table) {
            if (!Schema::hasColumn('cita_eventos', 'valor_anterior')) {
                $table->string('valor_anterior', 200)->nullable()->after('a_hora');
            }
            if (!Schema::hasColumn('cita_eventos', 'valor_nuevo')) {
                $table->string('valor_nuevo', 200)->nullable()->after('valor_anterior');
            }
            if (!Schema::hasColumn('cita_eventos', 'comentario')) {
                $table->string('comentario', 500)->nullable()->after('valor_nuevo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cita_eventos', function (Blueprint $table) {
            if (Schema::hasColumn('cita_eventos', 'comentario')) {
                $table->dropColumn('comentario');
            }
            if (Schema::hasColumn('cita_eventos', 'valor_nuevo')) {
                $table->dropColumn('valor_nuevo');
            }
            if (Schema::hasColumn('cita_eventos', 'valor_anterior')) {
                $table->dropColumn('valor_anterior');
            }
        });
    }
};


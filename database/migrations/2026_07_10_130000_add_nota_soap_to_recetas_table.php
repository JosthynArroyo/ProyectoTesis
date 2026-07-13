<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas', function (Blueprint $table): void {
            if (! Schema::hasColumn('recetas', 'nota_soap_id')) {
                $table->foreignId('nota_soap_id')
                    ->nullable()
                    ->after('cita_id')
                    ->constrained('notas_soap')
                    ->nullOnDelete()
                    ->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('recetas', function (Blueprint $table): void {
            if (Schema::hasColumn('recetas', 'nota_soap_id')) {
                $table->dropConstrainedForeignId('nota_soap_id');
            }
        });
    }
};

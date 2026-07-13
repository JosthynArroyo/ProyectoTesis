<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            $table->foreignId('dependiente_id')
                  ->nullable()
                  ->after('paciente_id')
                  ->constrained('dependientes')
                  ->nullOnDelete();
        });

        Schema::table('clinical_records', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->change();
            $table->foreignId('dependiente_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('dependientes')
                  ->cascadeOnDelete();
        });

        Schema::table('certificados_medicos', function (Blueprint $table) {
            $table->foreignId('dependiente_id')
                  ->nullable()
                  ->after('paciente_id')
                  ->constrained('dependientes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificados_medicos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dependiente_id');
        });

        Schema::table('clinical_records', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('dependiente_id');
        });

        Schema::table('citas_medicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dependiente_id');
        });
    }
};

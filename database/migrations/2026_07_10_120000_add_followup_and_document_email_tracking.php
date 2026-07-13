<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_soap', function (Blueprint $table): void {
            $table->foreignId('follow_up_cita_id')
                ->nullable()
                ->after('follow_up_notes')
                ->unique()
                ->constrained('citas_medicas')
                ->nullOnDelete();
        });

        Schema::table('citas_medicas', function (Blueprint $table): void {
            $table->foreignId('source_nota_soap_id')
                ->nullable()
                ->after('dependiente_id')
                ->unique()
                ->constrained('notas_soap')
                ->nullOnDelete();
        });

        Schema::table('certificados_medicos', function (Blueprint $table): void {
            $table->string('enviado_a')->nullable()->after('pdf_path');
            $table->timestamp('enviado_en')->nullable()->after('enviado_a');
            $table->string('envio_estado', 30)->default('pendiente')->after('enviado_en');
            $table->text('envio_error')->nullable()->after('envio_estado');
            $table->unsignedSmallInteger('envio_intentos')->default(0)->after('envio_error');
        });

        Schema::table('pedidos_laboratorio', function (Blueprint $table): void {
            $table->string('enviado_a')->nullable()->after('pdf_path');
            $table->timestamp('enviado_en')->nullable()->after('enviado_a');
            $table->string('envio_estado', 30)->default('pendiente')->after('enviado_en');
            $table->text('envio_error')->nullable()->after('envio_estado');
            $table->unsignedSmallInteger('envio_intentos')->default(0)->after('envio_error');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_laboratorio', function (Blueprint $table): void {
            $table->dropColumn(['enviado_a', 'enviado_en', 'envio_estado', 'envio_error', 'envio_intentos']);
        });

        Schema::table('certificados_medicos', function (Blueprint $table): void {
            $table->dropColumn(['enviado_a', 'enviado_en', 'envio_estado', 'envio_error', 'envio_intentos']);
        });

        Schema::table('citas_medicas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_nota_soap_id');
        });

        Schema::table('notas_soap', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('follow_up_cita_id');
        });
    }
};

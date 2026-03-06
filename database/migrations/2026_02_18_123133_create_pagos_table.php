<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas_medicas')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('monto', 10, 2)->default(0);
            $table->string('moneda', 3)->default('USD');
            $table->string('metodo_pago', 20)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('referencia_transaccion')->nullable();
            $table->text('observacion_admin')->nullable();
            $table->string('comprobante_path')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('cita_id');
            $table->index(['paciente_id', 'estado']);
            $table->index('created_at');
        });

        if (Schema::hasTable('citas_medicas') && Schema::hasTable('users')) {
            $now = now();
            $rows = DB::table('citas_medicas as c')
                ->leftJoin('users as d', 'd.id', '=', 'c.doctor_id')
                ->leftJoin('pagos as p', 'p.cita_id', '=', 'c.id')
                ->whereNull('p.id')
                ->orderBy('c.id')
                ->select([
                    'c.id',
                    'c.paciente_id',
                    DB::raw('COALESCE(d.precio_consulta, 0) as monto'),
                    DB::raw("COALESCE(d.moneda, 'USD') as moneda"),
                    'c.created_at',
                    'c.updated_at',
                ])
                ->get();

            foreach ($rows as $row) {
                DB::table('pagos')->insert([
                    'cita_id' => $row->id,
                    'paciente_id' => $row->paciente_id,
                    'monto' => $row->monto,
                    'moneda' => $row->moneda ?: 'USD',
                    'estado' => 'pendiente',
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $row->updated_at ?? $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

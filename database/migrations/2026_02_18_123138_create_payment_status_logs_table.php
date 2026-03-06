<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_rol', 40)->nullable();
            $table->text('motivo')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pago_id', 'created_at']);
            $table->index('estado_nuevo');
        });

        if (Schema::hasTable('pagos')) {
            $rows = DB::table('pagos')->select(['id', 'estado', 'created_at'])->get();
            foreach ($rows as $row) {
                DB::table('payment_status_logs')->insert([
                    'pago_id' => $row->id,
                    'estado_anterior' => null,
                    'estado_nuevo' => $row->estado ?: 'pendiente',
                    'actor_id' => null,
                    'actor_rol' => 'sistema',
                    'motivo' => 'Inicialización del estado de pago.',
                    'created_at' => $row->created_at ?? now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_status_logs');
    }
};

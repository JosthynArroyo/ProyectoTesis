<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table): void {
                if (! Schema::hasColumn('pagos', 'folio_unico')) {
                    $table->string('folio_unico', 60)->nullable()->after('cita_id');
                }
                if (! Schema::hasColumn('pagos', 'token_publico')) {
                    $table->string('token_publico', 120)->nullable()->after('folio_unico');
                }
                if (! Schema::hasColumn('pagos', 'orden_pdf_path')) {
                    $table->string('orden_pdf_path')->nullable()->after('comprobante_path');
                }
            });

            Schema::table('pagos', function (Blueprint $table): void {
                $table->unique('folio_unico');
                $table->unique('token_publico');
            });
        }

        if (! Schema::hasTable('payment_receipts')) {
            Schema::create('payment_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pago_id')->unique()->constrained('pagos')->cascadeOnDelete();
                $table->string('folio_recibo', 60)->unique();
                $table->timestamp('emitido_en');
                $table->foreignId('emitido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->string('metodo_pago', 20);
                $table->decimal('monto', 10, 2);
                $table->string('referencia_transaccion')->nullable();
                $table->string('comprobante_path')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamps();

                $table->index('emitido_en');
                $table->index('metodo_pago');
            });
        }

        if (! Schema::hasTable('payment_receipt_logs')) {
            Schema::create('payment_receipt_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payment_receipt_id')->constrained('payment_receipts')->cascadeOnDelete();
                $table->string('estado_anterior', 40)->nullable();
                $table->string('estado_nuevo', 40);
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_rol', 40)->nullable();
                $table->text('motivo')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['payment_receipt_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('pagos')) {
            return;
        }

        $pagosParaOrden = DB::table('pagos as p')
            ->join('citas_medicas as c', 'c.id', '=', 'p.cita_id')
            ->where(function ($query): void {
                $query->where('c.estado', '=', 'realizada')
                    ->orWhere('p.estado', '=', 'pagado');
            })
            ->select(['p.id', 'p.folio_unico', 'p.token_publico', 'c.fecha'])
            ->orderBy('p.id')
            ->get();

        foreach ($pagosParaOrden as $row) {
            $folio = $row->folio_unico;
            if (! $folio) {
                $fecha = $row->fecha ? date('Ymd', strtotime((string) $row->fecha)) : date('Ymd');
                $folio = sprintf('OC-%s-%06d', $fecha, (int) $row->id);
            }

            $token = $row->token_publico;
            if (! $token) {
                do {
                    $token = Str::lower(Str::random(48));
                } while (
                    DB::table('pagos')
                        ->where('token_publico', $token)
                        ->where('id', '!=', $row->id)
                        ->exists()
                );
            }

            DB::table('pagos')
                ->where('id', $row->id)
                ->update([
                    'folio_unico' => $folio,
                    'token_publico' => $token,
                ]);
        }

        if (! Schema::hasTable('payment_receipts')) {
            return;
        }

        $pagosPagados = DB::table('pagos')
            ->where('estado', 'pagado')
            ->orderBy('id')
            ->get();

        foreach ($pagosPagados as $pago) {
            $alreadyExists = DB::table('payment_receipts')
                ->where('pago_id', $pago->id)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $folioRecibo = sprintf('RP-%s-%06d', date('Ymd'), (int) $pago->id);
            if (DB::table('payment_receipts')->where('folio_recibo', $folioRecibo)->exists()) {
                $folioRecibo = sprintf('RP-%s-%06d-%d', date('Ymd'), (int) $pago->id, random_int(10, 99));
            }

            $emitidoEn = $pago->aprobado_en ?: $pago->updated_at ?: now();

            $reciboId = DB::table('payment_receipts')->insertGetId([
                'pago_id' => $pago->id,
                'folio_recibo' => $folioRecibo,
                'emitido_en' => $emitidoEn,
                'emitido_por' => $pago->aprobado_por,
                'metodo_pago' => $pago->metodo_pago ?: 'efectivo',
                'monto' => $pago->monto,
                'referencia_transaccion' => $pago->referencia_transaccion,
                'comprobante_path' => $pago->comprobante_path,
                'created_at' => $emitidoEn,
                'updated_at' => $emitidoEn,
            ]);

            DB::table('payment_receipt_logs')->insert([
                'payment_receipt_id' => $reciboId,
                'estado_anterior' => null,
                'estado_nuevo' => 'emitido',
                'actor_id' => $pago->aprobado_por,
                'actor_rol' => 'sistema',
                'motivo' => 'Inicializacion de recibo para pago historico.',
                'created_at' => $emitidoEn,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipt_logs');
        Schema::dropIfExists('payment_receipts');

        if (! Schema::hasTable('pagos')) {
            return;
        }

        Schema::table('pagos', function (Blueprint $table): void {
            if (Schema::hasColumn('pagos', 'folio_unico')) {
                $table->dropUnique(['folio_unico']);
            }
            if (Schema::hasColumn('pagos', 'token_publico')) {
                $table->dropUnique(['token_publico']);
            }
        });

        Schema::table('pagos', function (Blueprint $table): void {
            $toDrop = [];
            if (Schema::hasColumn('pagos', 'folio_unico')) {
                $toDrop[] = 'folio_unico';
            }
            if (Schema::hasColumn('pagos', 'token_publico')) {
                $toDrop[] = 'token_publico';
            }
            if (Schema::hasColumn('pagos', 'orden_pdf_path')) {
                $toDrop[] = 'orden_pdf_path';
            }

            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};

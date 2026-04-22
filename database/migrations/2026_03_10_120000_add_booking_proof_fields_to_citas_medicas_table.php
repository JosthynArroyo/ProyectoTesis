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
        if (! Schema::hasTable('citas_medicas')) {
            return;
        }

        Schema::table('citas_medicas', function (Blueprint $table): void {
            if (! Schema::hasColumn('citas_medicas', 'folio_cita')) {
                $table->string('folio_cita', 60)->nullable()->after('activo');
            }
            if (! Schema::hasColumn('citas_medicas', 'token_validacion')) {
                $table->string('token_validacion', 120)->nullable()->after('folio_cita');
            }
            if (! Schema::hasColumn('citas_medicas', 'comprobante_pdf_path')) {
                $table->string('comprobante_pdf_path')->nullable()->after('token_validacion');
            }
            if (! Schema::hasColumn('citas_medicas', 'comprobante_emitido_en')) {
                $table->timestamp('comprobante_emitido_en')->nullable()->after('comprobante_pdf_path');
            }
            if (! Schema::hasColumn('citas_medicas', 'comprobante_actualizado_en')) {
                $table->timestamp('comprobante_actualizado_en')->nullable()->after('comprobante_emitido_en');
            }
        });

        Schema::table('citas_medicas', function (Blueprint $table): void {
            $table->unique('folio_cita', 'citas_medicas_folio_cita_unique');
            $table->unique('token_validacion', 'citas_medicas_token_validacion_unique');
        });

        $citas = DB::table('citas_medicas')
            ->select([
                'id',
                'fecha',
                'folio_cita',
                'token_validacion',
                'comprobante_emitido_en',
                'comprobante_actualizado_en',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->get();

        foreach ($citas as $cita) {
            $folio = $cita->folio_cita ?: sprintf(
                'CC-%s-%06d',
                $cita->fecha ? date('Ymd', strtotime((string) $cita->fecha)) : date('Ymd'),
                (int) $cita->id
            );

            $token = $cita->token_validacion;
            if (! $token) {
                do {
                    $token = Str::lower(Str::random(48));
                } while (
                    DB::table('citas_medicas')
                        ->where('token_validacion', $token)
                        ->where('id', '!=', $cita->id)
                        ->exists()
                );
            }

            $marcaTiempo = $cita->updated_at ?: $cita->created_at ?: now();

            DB::table('citas_medicas')
                ->where('id', $cita->id)
                ->update([
                    'folio_cita' => $folio,
                    'token_validacion' => $token,
                    'comprobante_emitido_en' => $cita->comprobante_emitido_en ?: $marcaTiempo,
                    'comprobante_actualizado_en' => $cita->comprobante_actualizado_en ?: $marcaTiempo,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('citas_medicas')) {
            return;
        }

        Schema::table('citas_medicas', function (Blueprint $table): void {
            if (Schema::hasColumn('citas_medicas', 'folio_cita')) {
                $table->dropUnique('citas_medicas_folio_cita_unique');
            }
            if (Schema::hasColumn('citas_medicas', 'token_validacion')) {
                $table->dropUnique('citas_medicas_token_validacion_unique');
            }
        });

        Schema::table('citas_medicas', function (Blueprint $table): void {
            $columns = [];

            foreach ([
                'folio_cita',
                'token_validacion',
                'comprobante_pdf_path',
                'comprobante_emitido_en',
                'comprobante_actualizado_en',
            ] as $column) {
                if (Schema::hasColumn('citas_medicas', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

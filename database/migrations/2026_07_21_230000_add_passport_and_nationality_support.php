<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'tipo_documento')) {
                    $table->string('tipo_documento', 20)->default('cedula')->after('telefono');
                }
                if (! Schema::hasColumn('users', 'nacionalidad')) {
                    $table->string('nacionalidad', 10)->nullable()->after('tipo_documento');
                }
            });
        }

        if (Schema::hasTable('dependientes')) {
            Schema::table('dependientes', function (Blueprint $table) {
                if (! Schema::hasColumn('dependientes', 'tipo_documento')) {
                    $table->string('tipo_documento', 20)->default('cedula')->after('nombre');
                }
                if (! Schema::hasColumn('dependientes', 'nacionalidad')) {
                    $table->string('nacionalidad', 10)->nullable()->after('tipo_documento');
                }
            });
        }

        DB::table('users')->whereNull('tipo_documento')->orWhere('tipo_documento', '')->update(['tipo_documento' => 'cedula']);
        DB::table('dependientes')->whereNull('tipo_documento')->orWhere('tipo_documento', '')->update(['tipo_documento' => 'cedula']);

        if (Schema::hasTable('identity_documents')) {
            $users = DB::table('users')->whereNotNull('dni')->where('dni', '!=', '')->get();
            foreach ($users as $user) {
                $tipo = strtolower($user->tipo_documento ?? 'cedula') === 'pasaporte' ? 'PASAPORTE' : 'CEDULA';
                $pais = $tipo === 'PASAPORTE' ? strtoupper($user->nacionalidad ?? 'EC') : 'EC';
                $num = $tipo === 'PASAPORTE'
                    ? strtoupper(trim((string) $user->dni))
                    : preg_replace('/[^0-9]/', '', (string) $user->dni);

                if ($num !== '') {
                    DB::table('identity_documents')->updateOrInsert(
                        [
                            'documentable_type' => 'App\\Models\\User',
                            'documentable_id'   => $user->id,
                        ],
                        [
                            'pais'             => $pais,
                            'tipo_documento'   => $tipo,
                            'numero_documento' => $num,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]
                    );
                }
            }

            $dependientes = DB::table('dependientes')->whereNotNull('dni')->where('dni', '!=', '')->get();
            foreach ($dependientes as $dep) {
                $tipo = strtolower($dep->tipo_documento ?? 'cedula') === 'pasaporte' ? 'PASAPORTE' : 'CEDULA';
                $pais = $tipo === 'PASAPORTE' ? strtoupper($dep->nacionalidad ?? 'EC') : 'EC';
                $num = $tipo === 'PASAPORTE'
                    ? strtoupper(trim((string) $dep->dni))
                    : preg_replace('/[^0-9]/', '', (string) $dep->dni);

                if ($num !== '') {
                    DB::table('identity_documents')->updateOrInsert(
                        [
                            'documentable_type' => 'App\\Models\\Dependiente',
                            'documentable_id'   => $dep->id,
                        ],
                        [
                            'pais'             => $pais,
                            'tipo_documento'   => $tipo,
                            'numero_documento' => $num,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'tipo_documento')) {
                    $table->dropColumn('tipo_documento');
                }
                if (Schema::hasColumn('users', 'nacionalidad')) {
                    $table->dropColumn('nacionalidad');
                }
            });
        }

        if (Schema::hasTable('dependientes')) {
            Schema::table('dependientes', function (Blueprint $table) {
                if (Schema::hasColumn('dependientes', 'tipo_documento')) {
                    $table->dropColumn('tipo_documento');
                }
                if (Schema::hasColumn('dependientes', 'nacionalidad')) {
                    $table->dropColumn('nacionalidad');
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('identity_documents')) {
            Schema::create('identity_documents', function (Blueprint $table) {
                $table->id();
                $table->string('pais', 2)->default('EC');
                $table->string('tipo_documento', 20)->default('CEDULA');
                $table->string('numero_documento', 20);
                $table->string('documentable_type', 255);
                $table->unsignedBigInteger('documentable_id');
                $table->timestamps();

                $table->unique(['pais', 'tipo_documento', 'numero_documento'], 'identity_docs_unique_doc');
                $table->index(['documentable_type', 'documentable_id'], 'identity_docs_morph_idx');
            });
        }

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'dni', DB::raw("'App\\\\Models\\\\User' as entity_type"))
            ->whereNotNull('dni')
            ->where('dni', '!=', '')
            ->get();

        $dependientes = DB::table('dependientes')
            ->select('id', 'nombre as name', DB::raw('NULL as email'), 'dni', DB::raw("'App\\\\Models\\\\Dependiente' as entity_type"))
            ->whereNotNull('dni')
            ->where('dni', '!=', '')
            ->get();

        $all = $users->concat($dependientes);

        $grouped = $all->groupBy(function ($item) {
            return preg_replace('/[^0-9]/', '', (string) $item->dni);
        });

        $duplicates = $grouped->filter(function ($group) {
            return $group->count() > 1;
        });

        if ($duplicates->isNotEmpty()) {
            $conflicts = [];
            foreach ($duplicates as $dni => $items) {
                $details = [];
                foreach ($items as $item) {
                    $details[] = "{$item->name} ({$item->entity_type} ID {$item->id}, Email: " . ($item->email ?? 'N/A') . ")";
                }
                $conflicts[] = "Cédula {$dni}: " . implode(' VS ', $details);
            }
            $report = implode("\n", $conflicts);
            throw new \RuntimeException("ERROR DE MIGRACIÓN: Se encontraron documentos de identidad duplicados en la base de datos:\n" . $report . "\nPor favor corrija los duplicados antes de aplicar esta restricción de unicidad.");
        }

        foreach ($all as $item) {
            $normalized = preg_replace('/[^0-9]/', '', (string) $item->dni);
            if ($normalized !== '') {
                DB::table('identity_documents')->updateOrInsert(
                    [
                        'documentable_type' => $item->entity_type,
                        'documentable_id'   => $item->id,
                    ],
                    [
                        'pais'             => 'EC',
                        'tipo_documento'   => 'CEDULA',
                        'numero_documento' => $normalized,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_documents');
    }
};

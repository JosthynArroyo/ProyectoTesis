<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_soap', function (Blueprint $table) {
            $table->foreignId('clinical_record_id')->nullable()->constrained('clinical_records')->nullOnDelete();
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
        });

        Schema::table('recetas', function (Blueprint $table) {
            $table->foreignId('clinical_record_id')->nullable()->constrained('clinical_records')->nullOnDelete();
        });

        Schema::table('laboratorio_ordenes', function (Blueprint $table) {
            $table->foreignId('clinical_record_id')->nullable()->constrained('clinical_records')->nullOnDelete();
        });

        Schema::table('medical_orders', function (Blueprint $table) {
            $table->foreignId('clinical_record_id')->nullable()->constrained('clinical_records')->nullOnDelete();
        });

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->foreignId('clinical_record_id')->nullable()->constrained('clinical_records')->nullOnDelete();
        });

        $this->backfillClinicalRecords();
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_record_id');
        });

        Schema::table('medical_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_record_id');
        });

        Schema::table('laboratorio_ordenes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_record_id');
        });

        Schema::table('recetas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_record_id');
        });

        Schema::table('notas_soap', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinical_record_id');
            $table->dropColumn(['follow_up_date', 'follow_up_notes']);
        });
    }

    private function backfillClinicalRecords(): void
    {
        $patientIds = $this->collectPatientIds();
        if ($patientIds->isEmpty()) {
            return;
        }

        $timestamp = now();

        foreach ($patientIds as $patientId) {
            DB::table('clinical_records')->updateOrInsert(
                ['patient_id' => $patientId],
                [
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );
        }

        $recordIds = DB::table('clinical_records')
            ->whereIn('patient_id', $patientIds->all())
            ->pluck('id', 'patient_id');

        DB::table('citas_medicas')
            ->select(['id', 'paciente_id'])
            ->orderBy('id')
            ->chunk(200, function (Collection $citas) use ($recordIds): void {
                foreach ($citas as $cita) {
                    $recordId = $recordIds[$cita->paciente_id] ?? null;
                    if (! $recordId) {
                        continue;
                    }

                    DB::table('notas_soap')
                        ->where('cita_id', $cita->id)
                        ->update(['clinical_record_id' => $recordId]);

                    DB::table('recetas')
                        ->where('cita_id', $cita->id)
                        ->update(['clinical_record_id' => $recordId]);

                    DB::table('laboratorio_ordenes')
                        ->where('cita_id', $cita->id)
                        ->update(['clinical_record_id' => $recordId]);
                }
            });

        foreach ($recordIds as $patientId => $recordId) {
            DB::table('medical_orders')
                ->where('patient_id', $patientId)
                ->update(['clinical_record_id' => $recordId]);

            DB::table('lab_orders')
                ->where('patient_id', $patientId)
                ->update(['clinical_record_id' => $recordId]);
        }

        DB::table('notas_soap')
            ->whereNull('follow_up_notes')
            ->whereNotNull('plan_seguimiento')
            ->update(['follow_up_notes' => DB::raw('plan_seguimiento')]);
    }

    private function collectPatientIds(): Collection
    {
        return collect()
            ->merge(DB::table('citas_medicas')->pluck('paciente_id'))
            ->merge(DB::table('medical_orders')->pluck('patient_id'))
            ->merge(DB::table('lab_orders')->pluck('patient_id'))
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();
    }
};

<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\User;
use App\Services\PriorityEvaluator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CitaPrioridadRulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cita_con_red_flag_queda_en_prioridad_alta_automatica(): void
    {
        $paciente = $this->createUserWithRole('paciente', [
            'fecha_nacimiento' => now()->subYears(30)->toDateString(),
        ]);
        $this->createPatientFlags($paciente, [
            'embarazo' => false,
            'discapacidad' => false,
            'cronico' => false,
        ]);

        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'motivo_consulta' => 'Dolor de pecho y sudoracion',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        app(PriorityEvaluator::class)->apply($cita);
        $cita->save();
        $cita->refresh();

        $this->assertSame(Cita::PRIORIDAD_ALTA, $cita->prioridad_nivel);
        $this->assertSame(Cita::FUENTE_PRIORIDAD_REGLA_RED_FLAG, $cita->prioridad_fuente);
        $this->assertTrue($cita->prioridad_red_flag);
        $this->assertSame('dolor_pecho', $cita->prioridad_red_flag_tipo);
    }

    public function test_cita_sin_red_flag_pero_con_paciente_vulnerable_queda_media(): void
    {
        $paciente = $this->createUserWithRole('paciente', [
            'fecha_nacimiento' => now()->subYears(70)->toDateString(),
        ]);
        $this->createPatientFlags($paciente, [
            'embarazo' => false,
            'discapacidad' => false,
            'cronico' => false,
        ]);

        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'motivo_consulta' => 'Control de presion arterial',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        app(PriorityEvaluator::class)->apply($cita);
        $cita->save();
        $cita->refresh();

        $this->assertSame(Cita::PRIORIDAD_MEDIA, $cita->prioridad_nivel);
        $this->assertSame(Cita::FUENTE_PRIORIDAD_REGLA_VULNERABILIDAD, $cita->prioridad_fuente);
        $this->assertFalse($cita->prioridad_red_flag);
        $this->assertNull($cita->prioridad_red_flag_tipo);
        $this->assertTrue($cita->prioridad_es_adulto_mayor);
    }

    public function test_cita_normal_queda_baja_automatica(): void
    {
        $paciente = $this->createUserWithRole('paciente', [
            'fecha_nacimiento' => now()->subYears(25)->toDateString(),
        ]);
        $this->createPatientFlags($paciente, [
            'embarazo' => false,
            'discapacidad' => false,
            'cronico' => false,
        ]);

        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'motivo_consulta' => 'Revision general',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        app(PriorityEvaluator::class)->apply($cita);
        $cita->save();
        $cita->refresh();

        $this->assertSame(Cita::PRIORIDAD_BAJA, $cita->prioridad_nivel);
        $this->assertSame(Cita::FUENTE_PRIORIDAD_AUTOMATICA, $cita->prioridad_fuente);
        $this->assertFalse($cita->prioridad_red_flag);
        $this->assertNull($cita->prioridad_red_flag_tipo);
    }

    public function test_override_manual_exige_comentario_y_registra_auditoria(): void
    {
        $admin = $this->createUserWithRole('administrador');

        $paciente = $this->createUserWithRole('paciente', [
            'fecha_nacimiento' => now()->subYears(35)->toDateString(),
        ]);
        $this->createPatientFlags($paciente, [
            'embarazo' => false,
            'discapacidad' => false,
            'cronico' => false,
        ]);

        $doctor = $this->createUserWithRole('doctor');
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'motivo_consulta' => 'Dolor toracico persistente',
            'estado' => Cita::ESTADO_PENDIENTE,
            'activo' => true,
        ]);

        app(PriorityEvaluator::class)->apply($cita);
        $cita->save();
        $cita->refresh();
        $this->assertSame(Cita::PRIORIDAD_ALTA, $cita->prioridad_nivel);

        $this->actingAs($admin)
            ->from(route('admin.citas.prioridad.edit', $cita))
            ->patch(route('admin.citas.prioridad.update', $cita), [
                'prioridad_nivel' => Cita::PRIORIDAD_MEDIA,
            ])
            ->assertSessionHasErrors('prioridad_comentario');

        $this->actingAs($admin)
            ->from(route('admin.citas.prioridad.edit', $cita))
            ->patch(route('admin.citas.prioridad.update', $cita), [
                'prioridad_nivel' => Cita::PRIORIDAD_ALTA,
                'ignorar_red_flag' => 1,
            ])
            ->assertSessionHasErrors('prioridad_comentario');

        $this->actingAs($admin)
            ->patch(route('admin.citas.prioridad.update', $cita), [
                'prioridad_nivel' => Cita::PRIORIDAD_BAJA,
                'ignorar_red_flag' => 1,
                'prioridad_comentario' => 'Paciente evaluado y estable. Se remite a consulta ordinaria.',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $cita->refresh();

        $this->assertSame(Cita::PRIORIDAD_BAJA, $cita->prioridad_nivel);
        $this->assertSame(Cita::FUENTE_PRIORIDAD_MANUAL, $cita->prioridad_fuente);
        $this->assertFalse($cita->prioridad_red_flag);
        $this->assertNull($cita->prioridad_red_flag_tipo);
        $this->assertNotNull($cita->prioridad_comentario);

        $evento = CitaEvento::query()
            ->where('cita_id', $cita->id)
            ->where('tipo', 'prioridad_manual')
            ->latest('id')
            ->first();

        $this->assertNotNull($evento);
        $this->assertSame($admin->id, $evento->user_id);
        $this->assertSame(Cita::PRIORIDAD_ALTA, $evento->de_estado);
        $this->assertSame(Cita::PRIORIDAD_BAJA, $evento->a_estado);
        $this->assertStringContainsString('NIVEL:ALTA', (string) $evento->valor_anterior);
        $this->assertStringContainsString('NIVEL:BAJA', (string) $evento->valor_nuevo);
        $this->assertNotNull($evento->comentario);
    }

    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'active' => true,
            'dni' => fake()->numerify('##########'),
            'telefono' => fake()->numerify('##########'),
        ], $attributes));

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createPatientFlags(User $patient, array $flags): void
    {
        $patient->patientFlag()->create(array_merge([
            'adulto_mayor' => false,
            'embarazo' => false,
            'discapacidad' => false,
            'cronico' => false,
        ], $flags));
    }
}

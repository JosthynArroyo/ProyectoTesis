<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\FaceProfile;
use App\Models\Horario;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UxAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_superadmin_can_update_admin_without_sending_a_new_password(): void
    {
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);

        $superadmin = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('superadmin1234'),
        ]);
        $superadmin->roles()->attach($superadminRole->id);

        $admin = User::factory()->create([
            'name' => 'Admin Original',
            'email' => 'admin.original@example.test',
            'telefono' => '0990000001',
            'dni' => '0912345675',
            'direccion' => 'Calle 1',
            'fecha_nacimiento' => '1990-01-10',
            'sexo' => 'Masculino',
            'status' => 'active',
            'password' => Hash::make('admin1234*'),
        ]);
        $admin->roles()->attach($adminRole->id);

        $oldPasswordHash = $admin->password;

        $this->actingAs($superadmin)
            ->put(route('superadmin.admins.update', $admin), [
                'name' => 'Admin Actualizado',
                'email' => 'admin.actualizado@example.test',
                'telefono' => '0990000002',
                'dni' => '1754504635',
                'direccion' => 'Calle 2',
                'fecha_nacimiento' => '1990-01-10',
                'sexo' => 'Femenino',
            ])
            ->assertRedirect(route('superadmin.admins.edit', $admin));

        $admin->refresh();

        $this->assertSame('Admin Actualizado', $admin->name);
        $this->assertSame('admin.actualizado@example.test', $admin->email);
        $this->assertSame('0990000002', $admin->telefono);
        $this->assertSame($oldPasswordHash, $admin->password);
    }

    public function test_patient_booking_and_doctor_schedule_pages_render_native_date_inputs(): void
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create(['status' => 'active']);
        $patient->roles()->attach($patientRole->id);

        $doctor = User::factory()->create(['status' => 'active']);
        $doctor->roles()->attach($doctorRole->id);

        $this->actingAs($patient)
            ->get(route('paciente.crear-cita'))
            ->assertOk()
            ->assertSee('type="date"', false)
            ->assertSee('data-native-date-open="#fecha"', false)
            ->assertDontSee('data-enhanced-date', false);

        $this->actingAs($doctor)
            ->get(route('doctor.horario.index'))
            ->assertOk()
            ->assertSee('type="date"', false)
            ->assertSee('data-native-date-open="#fecha_single"', false)
            ->assertDontSee('data-enhanced-date', false);
    }

    public function test_doctor_soap_height_field_labels_centimeters_and_normalizes_meter_input(): void
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create(['status' => 'active']);
        $patient->roles()->attach($patientRole->id);

        $doctor = User::factory()->create(['status' => 'active']);
        $doctor->roles()->attach($doctorRole->id);

        $cita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => Especialidad::factory()->create()->id,
            'estado' => Cita::ESTADO_CONFIRMADA,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Dolor abdominal desde ayer',
        ]);

        $this->actingAs($doctor)
            ->get(route('doctor.citas.soap', $cita))
            ->assertOk()
            ->assertSee('step="0.01"', false)
            ->assertSee('id="sv_talla_unit"', false)
            ->assertSee('>cm<', false)
            ->assertSee('Dolor abdominal desde ayer');

        $this->actingAs($doctor)
            ->post(route('doctor.citas.soap.store', $cita), [
                'sv_talla' => '1.63',
            ])
            ->assertRedirect(route('doctor.citas.soap', $cita->id));

        $nota = NotaSoap::where('cita_id', $cita->id)->firstOrFail();

        $this->assertSame('163', (string) data_get($nota->signos_vitales, 'talla'));
        $this->assertSame('Dolor abdominal desde ayer', $nota->subjetivo_motivo);
    }

    public function test_doctor_soap_shows_existing_control_and_allows_reschedule_or_cancel(): void
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create(['status' => 'active']);
        $patient->roles()->attach($patientRole->id);

        $doctor = User::factory()->create(['status' => 'active']);
        $doctor->roles()->attach($doctorRole->id);

        $specialty = Especialidad::factory()->create();
        $baseDate = Carbon::now('America/Guayaquil')->addDays(3)->toDateString();
        $controlDate = Carbon::now('America/Guayaquil')->addDays(6)->toDateString();
        $newControlDate = Carbon::now('America/Guayaquil')->addDays(8)->toDateString();

        $cita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'estado' => Cita::ESTADO_CONFIRMADA,
            'fecha' => $baseDate,
            'hora' => '09:00:00',
            'motivo_consulta' => 'Seguimiento de control',
        ]);

        NotaSoap::create([
            'cita_id' => $cita->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctor->id,
            'subjetivo_motivo' => 'Seguimiento de control',
        ]);

        $control = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $specialty->id,
            'estado' => Cita::ESTADO_PENDIENTE,
            'fecha' => $controlDate,
            'hora' => '11:00:00',
            'motivo_consulta' => 'Seguimiento de control',
            'activo' => true,
        ]);

        Horario::create([
            'doctor_id' => $doctor->id,
            'fecha' => $newControlDate,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'intervalo_minutos' => 30,
        ]);

        $this->actingAs($doctor)
            ->get(route('doctor.citas.soap', $cita))
            ->assertOk()
            ->assertSee('Control agendado')
            ->assertSee(Carbon::parse($controlDate)->format('d/m/Y'))
            ->assertSee('Reagendar control')
            ->assertSee('Cancelar control');

        $this->actingAs($doctor)
            ->postJson(route('doctor.citas.proxima.planificada', $cita), [
                'fecha' => $newControlDate,
                'hora' => '10:30',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('msg', 'Control reagendado correctamente.');

        $control->refresh();
        $this->assertSame($newControlDate, Carbon::parse($control->fecha)->toDateString());
        $this->assertSame('10:30:00', $control->hora);
        $this->assertSame(1, Cita::query()
            ->where('paciente_id', $patient->id)
            ->where('doctor_id', $doctor->id)
            ->where('id', '<>', $cita->id)
            ->count());

        $this->actingAs($doctor)
            ->post(route('doctor.citas.proxima.cancelar', ['cita' => $cita, 'control' => $control]))
            ->assertRedirect(route('doctor.citas.soap', $cita));

        $control->refresh();
        $this->assertSame(Cita::ESTADO_CANCELADA, $control->estado);
        $this->assertFalse((bool) $control->activo);
    }

    public function test_recipe_create_prefills_only_diagnosis_from_signed_clinical_note(): void
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);

        $patient = User::factory()->create(['status' => 'active']);
        $patient->roles()->attach($patientRole->id);

        $doctor = User::factory()->create(['status' => 'active']);
        $doctor->roles()->attach($doctorRole->id);

        $cita = Cita::factory()->create([
            'paciente_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => Especialidad::factory()->create()->id,
            'estado' => Cita::ESTADO_REALIZADA,
            'fecha' => now()->toDateString(),
            'hora' => '09:00:00',
            'motivo_consulta' => 'Dolor de cabeza reportado al agendar',
        ]);

        $nota = NotaSoap::create([
            'cita_id' => $cita->id,
            'estado' => NotaSoap::ESTADO_FIRMADA,
            'signed_at' => now(),
            'signed_by' => $doctor->id,
            'subjetivo_motivo' => 'Cefalea persistente registrada en consulta',
            'assessment' => 'Paciente estable',
        ]);

        NotaSoapDiagnostico::create([
            'nota_soap_id' => $nota->id,
            'tipo' => 'principal',
            'texto' => 'Cefalea tensional',
            'cie10' => 'R51',
        ]);

        $this->actingAs($doctor)
            ->get(route('doctor.recetas.create', $cita))
            ->assertOk()
            ->assertSee('Diagnostico principal: Cefalea tensional (CIE-10: R51)')
            ->assertDontSee('Motivo:')
            ->assertDontSee('Cefalea persistente registrada en consulta')
            ->assertDontSee('Dolor de cabeza reportado al agendar')
            ->assertSee('Tomado de los diagnosticos de la nota clinica firmada');
    }

    public function test_stat_component_keeps_zero_values_visible(): void
    {
        $html = Blade::render('<x-ui.stat label="Pendientes" :value="$value" />', [
            'value' => 0,
        ]);

        $this->assertStringContainsString('Pendientes', $html);
        $this->assertStringContainsString('>0<', $html);
    }

    public function test_public_footer_legal_buttons_render_openable_modals(): void
    {
        $this->get(route('home.index'))
            ->assertOk()
            ->assertSee('data-legal-open="privacy-policy-modal"', false)
            ->assertSee('id="privacy-policy-modal"', false)
            ->assertSee('window.__legalModalsInitialized', false)
            ->assertSee('window.openLegalModal', false);
    }



    public function test_profile_pages_render_birth_date_parts_inputs(): void
    {
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);

        $patient = User::factory()->create(['status' => 'active']);
        $patient->roles()->attach($patientRole->id);

        $superadmin = User::factory()->create(['status' => 'active']);
        $superadmin->roles()->attach($superadminRole->id);

        $this->actingAs($patient)
            ->get(route('paciente.perfil.edit'))
            ->assertOk()
            ->assertSee('name="fecha_nacimiento_day"', false)
            ->assertSee('name="fecha_nacimiento_month"', false)
            ->assertSee('name="fecha_nacimiento_year"', false)
            ->assertDontSee('type="date"', false);

        $this->actingAs($superadmin)
            ->get(route('superadmin.admins.create'))
            ->assertOk()
            ->assertSee('name="fecha_nacimiento_day"', false)
            ->assertSee('name="fecha_nacimiento_month"', false)
            ->assertSee('name="fecha_nacimiento_year"', false)
            ->assertDontSee('type="date"', false);
    }

    public function test_main_dashboards_do_not_render_decorative_date_inputs(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $patientRole = Role::firstOrCreate(['name' => 'paciente']);
        $labRole = Role::firstOrCreate(['name' => 'laboratorio']);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->attach($adminRole->id);

        $doctor = User::factory()->create(['status' => 'active']);
        $doctor->roles()->attach($doctorRole->id);

        $patient = User::factory()->create(['status' => 'active']);
        $patient->roles()->attach($patientRole->id);

        $laboratory = User::factory()->create(['status' => 'active']);
        $laboratory->roles()->attach($labRole->id);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('type="date"', false);

        $this->actingAs($doctor)
            ->get(route('doctor.dashboard'))
            ->assertOk()
            ->assertDontSee('type="date"', false);

        $this->actingAs($patient)
            ->get(route('paciente.dashboard'))
            ->assertOk()
            ->assertDontSee('type="date"', false);

        $this->actingAs($laboratory)
            ->get(route('laboratorio.dashboard'))
            ->assertOk()
            ->assertDontSee('type="date"', false);
    }
}

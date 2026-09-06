<?php

namespace Tests\Feature;

use App\Models\Dependiente;
use App\Models\IdentityDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\IdentityDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GlobalDniUniquenessTest extends TestCase
{
    use DatabaseTransactions;

    private User $titular;
    private Role $rolePaciente;
    private Role $roleDoctor;
    private Role $roleAdmin;
    private Role $roleLab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolePaciente = Role::firstOrCreate(['name' => 'paciente']);
        $this->roleDoctor = Role::firstOrCreate(['name' => 'doctor']);
        $this->roleAdmin = Role::firstOrCreate(['name' => 'administrador']);
        $this->roleLab = Role::firstOrCreate(['name' => 'laboratorio']);

        $this->titular = User::factory()->create([
            'name' => 'Josthyn Arroyo',
            'email' => 'josthyn.test@clinica.test',
            'dni' => '1754504635',
            'tipo_documento' => 'cedula',
        ]);
        $this->titular->roles()->attach($this->rolePaciente);
    }

    /** 1. Mandatory Case: Crear un dependiente con la cédula del titular */
    #[Test]
    public function mandatory_case_cannot_create_dependent_with_titular_dni()
    {
        $response = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Doménica',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'fecha_nacimiento' => '2015-05-10',
            'sexo' => 'Femenino',
            'parentesco' => 'hija',
        ]);

        $response->assertSessionHasErrors(['dni']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('dni') === 'Este documento ya está registrado para otra persona.';
        });

        $this->assertDatabaseMissing('dependientes', ['nombre' => 'Doménica']);
        $this->assertEquals(1, User::where('dni', '1754504635')->count() + Dependiente::where('dni', '1754504635')->count());
    }

    /** 2. Cédula de 10 dígitos sin verificar checksum ecuatoriano es aceptada */
    #[Test]
    public function cedula_10_digits_without_checksum_is_accepted()
    {
        $response = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Mateo Válido',
            'tipo_documento' => 'cedula',
            'dni' => '1721820659',
            'fecha_nacimiento' => '2016-01-01',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dependientes', ['nombre' => 'Mateo Válido', 'dni' => '1721820659']);
    }

    /** 3. Cédula que no contenga exactamente 10 dígitos es rechazada */
    #[Test]
    public function cedula_not_10_digits_is_rejected()
    {
        $response = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Inválido',
            'tipo_documento' => 'cedula',
            'dni' => '12345',
            'fecha_nacimiento' => '2016-01-01',
            'parentesco' => 'hijo',
        ]);

        $response->assertSessionHasErrors(['dni']);
        $response->assertSessionHas('errors', function ($errors) {
            return $errors->first('dni') === 'La cédula debe contener exactamente 10 dígitos.';
        });
    }

    /** 4. Pasaporte requiere nacionalidad y formato válido de 5 a 20 caracteres */
    #[Test]
    public function passport_requires_nationality_and_valid_format()
    {
        // Sin nacionalidad
        $resNoNac = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Extranjero 1',
            'tipo_documento' => 'pasaporte',
            'dni' => 'AB123456',
            'fecha_nacimiento' => '2016-01-01',
            'parentesco' => 'hijo',
        ]);
        $resNoNac->assertSessionHasErrors(['nacionalidad']);

        // Formato inválido
        $resBadFormat = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Extranjero 2',
            'tipo_documento' => 'pasaporte',
            'nacionalidad' => 'CO',
            'dni' => 'AB',
            'fecha_nacimiento' => '2016-01-01',
            'parentesco' => 'hijo',
        ]);
        $resBadFormat->assertSessionHasErrors(['dni']);
        $resBadFormat->assertSessionHas('errors', function ($errors) {
            return $errors->first('dni') === 'El pasaporte debe contener entre 5 y 20 caracteres, utilizando letras, números o guion.';
        });
    }

    /** 5. Pasaportes de distintos países pueden compartir número, pero del mismo país son únicos */
    #[Test]
    public function passport_uniqueness_is_scoped_by_nationality()
    {
        // Crear pasaporte de Colombia
        $depCol = Dependiente::create([
            'user_id' => $this->titular->id,
            'nombre' => 'Carlos Colombiano',
            'tipo_documento' => 'pasaporte',
            'nacionalidad' => 'CO',
            'dni' => 'PAS12345',
            'fecha_nacimiento' => '2016-01-01',
            'parentesco' => 'hijo',
        ]);

        // Mismo pasaporte para Colombia -> Rechazado
        $resSameCountry = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Otro Colombiano',
            'tipo_documento' => 'pasaporte',
            'nacionalidad' => 'CO',
            'dni' => 'PAS12345',
            'fecha_nacimiento' => '2017-02-02',
            'parentesco' => 'hijo',
        ]);
        $resSameCountry->assertSessionHasErrors(['dni']);

        // Mismo pasaporte para Perú -> Aceptado
        $resDiffCountry = $this->actingAs($this->titular)->post(route('paciente.dependientes.store'), [
            'nombre' => 'Peruano Con Mismo Numero',
            'tipo_documento' => 'pasaporte',
            'nacionalidad' => 'PE',
            'dni' => 'PAS12345',
            'fecha_nacimiento' => '2017-02-02',
            'sexo' => 'Masculino',
            'parentesco' => 'hijo',
        ]);
        $resDiffCountry->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dependientes', ['nombre' => 'Peruano Con Mismo Numero', 'nacionalidad' => 'PE', 'dni' => 'PAS12345']);
    }

    /** 6. Chatbot con cédula existente */
    #[Test]
    public function chatbot_rejects_duplicate_dni()
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureCaptchaVerified::class]);

        $response = $this->postJson('/chatbot/registrar-usuario', [
            'nombre' => 'Intento Chatbot',
            'tipo_documento' => 'cedula',
            'cedula' => '1754504635',
            'email' => 'chatbot.dup@test.com',
            'telefono' => '0998887766',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cedula']);
    }

    /** 7. Editar una persona conservando su propia cédula */
    #[Test]
    public function person_can_keep_own_dni_when_editing()
    {
        $response = $this->actingAs($this->titular)->post(route('paciente.perfil.update'), [
            'name' => 'Josthyn Arroyo Modificado',
            'email' => $this->titular->email,
            'telefono' => '0998740927',
            'tipo_documento' => 'cedula',
            'dni' => '1754504635',
            'direccion' => 'Dirección Nueva',
            'fecha_nacimiento' => '2005-04-30',
            'sexo' => 'Masculino',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Josthyn Arroyo Modificado', $this->titular->fresh()->name);
    }

    /** 8. DB-level unique constraint prevents duplicate insertion */
    #[Test]
    public function database_unique_constraint_prevents_duplicate_insertion()
    {
        $this->expectException(\Throwable::class);

        IdentityDocument::create([
            'pais' => 'EC',
            'tipo_documento' => 'CEDULA',
            'numero_documento' => '1754504635',
            'documentable_type' => Dependiente::class,
            'documentable_id' => 999,
        ]);
    }

    /** 9. El componente document-fields usa class hidden condicional sin style inline */
    #[Test]
    public function document_fields_component_uses_declarative_hidden_class_without_inline_styles(): void
    {
        // 1. SSR con tipo cédula por defecto (nacionalidad con class="hidden")
        $response = $this->actingAs($this->titular)->get(route('paciente.dependientes.create'));
        $response->assertOk()
            ->assertSee('data-nacionalidad-wrap', false)
            ->assertSee('class="hidden" data-nacionalidad-wrap', false)
            ->assertDontSee('style="display: none;"', false)
            ->assertDontSee('style=""', false);

        // 2. SSR tras error de validación con pasaporte (nacionalidad sin class="hidden")
        $postResponse = $this->actingAs($this->titular)
            ->from(route('paciente.dependientes.create'))
            ->post(route('paciente.dependientes.store'), [
                'nombre' => '', // Falla validación requerida
                'tipo_documento' => 'pasaporte',
                'nacionalidad' => 'US',
                'dni' => 'AB123456',
                'fecha_nacimiento' => '2015-05-10',
                'sexo' => 'Femenino',
                'parentesco' => 'hija',
            ]);

        $postResponse->assertRedirect(route('paciente.dependientes.create'))
            ->assertSessionHasErrors(['nombre']);

        $followResponse = $this->actingAs($this->titular)
            ->get(route('paciente.dependientes.create'));

        $followResponse->assertOk()
            ->assertSee('class="" data-nacionalidad-wrap', false)
            ->assertDontSee('style="display: none;"', false);

        // 3. SSR tras error de validación con cédula (nacionalidad con class="hidden")
        $postCedulaResponse = $this->actingAs($this->titular)
            ->from(route('paciente.dependientes.create'))
            ->post(route('paciente.dependientes.store'), [
                'nombre' => '', // Falla validación requerida
                'tipo_documento' => 'cedula',
                'dni' => '1754504635',
                'fecha_nacimiento' => '2015-05-10',
                'sexo' => 'Femenino',
                'parentesco' => 'hija',
            ]);

        $postCedulaResponse->assertRedirect(route('paciente.dependientes.create'))
            ->assertSessionHasErrors(['nombre']);

        $followCedulaResponse = $this->actingAs($this->titular)
            ->get(route('paciente.dependientes.create'));

        $followCedulaResponse->assertOk()
            ->assertSee('class="hidden" data-nacionalidad-wrap', false)
            ->assertDontSee('style="display: none;"', false)
            ->assertDontSee('style=""', false);

        // 4. Inspección estática de Blade y JS
        $bladePath = resource_path('views/components/ui/document-fields.blade.php');
        $this->assertFileExists($bladePath);
        $blade = file_get_contents($bladePath);
        $this->assertStringNotContainsString('style=', $blade);

        $jsPath = resource_path('js/forms/document-fields.js');
        $this->assertFileExists($jsPath);
        $js = file_get_contents($jsPath);
        $this->assertStringContainsString("nacWrap.classList.toggle('hidden', !isPasaporte)", $js);
        $this->assertStringNotContainsString('style.display', $js);
    }
}

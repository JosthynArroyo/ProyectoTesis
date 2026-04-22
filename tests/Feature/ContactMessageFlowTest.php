<?php

namespace Tests\Feature;

use App\Mail\ContactoRecibido;
use App\Models\ContactMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactMessageFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_saves_sends_mail_and_is_visible_in_admin_panel(): void
    {
        Mail::fake();
        config(['mail.contact_to' => 'admin@example.test']);

        $payload = [
            'nombre' => 'Paciente Prueba',
            'email' => 'paciente@example.test',
            'telefono' => '0991234567',
            'asunto' => 'Consulta de horarios',
            'mensaje' => 'Necesito informacion sobre horarios de atencion.',
            'empresa' => '',
            't0' => now()->subSeconds(5)->timestamp,
        ];

        $this->from('/contacto')
            ->post('/contacto', $payload)
            ->assertRedirect('/contacto')
            ->assertSessionHas('success', 'Tu mensaje ha sido enviado correctamente.');

        $this->assertDatabaseHas('contact_messages', [
            'nombre' => 'Paciente Prueba',
            'correo' => 'paciente@example.test',
            'telefono' => '0991234567',
            'asunto' => 'Consulta de horarios',
            'estado' => 'nuevo',
        ]);

        Mail::assertSent(ContactoRecibido::class, function (ContactoRecibido $mail): bool {
            return $mail->hasTo('admin@example.test')
                && ($mail->datos['email'] ?? null) === 'paciente@example.test';
        });

        $role = Role::query()->firstOrCreate(['name' => 'administrador']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $message = ContactMessage::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.contacto.mensajes'))
            ->assertOk()
            ->assertSee('Paciente Prueba')
            ->assertSee('Consulta de horarios');

        $this->actingAs($admin)
            ->get(route('admin.contacto.mensajes.show', $message))
            ->assertOk()
            ->assertSee('Necesito informacion sobre horarios de atencion.');
    }

    public function test_contact_form_keeps_message_saved_when_mail_delivery_fails(): void
    {
        config(['mail.contact_to' => 'admin@example.test']);

        Mail::shouldReceive('to')
            ->once()
            ->with('admin@example.test')
            ->andThrow(new \RuntimeException('SMTP unavailable'));

        $payload = [
            'nombre' => 'Paciente Sin Correo',
            'email' => 'sin-correo@example.test',
            'telefono' => '0997654321',
            'asunto' => 'Consulta administrativa',
            'mensaje' => 'Quiero confirmar si mi mensaje queda registrado.',
            'empresa' => '',
            't0' => now()->subSeconds(5)->timestamp,
        ];

        $this->from('/contacto')
            ->post('/contacto', $payload)
            ->assertRedirect('/contacto')
            ->assertSessionHas('success', 'Tu mensaje ha sido enviado correctamente.');

        $this->assertDatabaseHas('contact_messages', [
            'nombre' => 'Paciente Sin Correo',
            'correo' => 'sin-correo@example.test',
            'telefono' => '0997654321',
            'asunto' => 'Consulta administrativa',
            'estado' => 'nuevo',
        ]);
    }
}

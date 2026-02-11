<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotIdentityFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_verificar_paciente_acepta_cedula_sin_correo(): void
    {
        $rolPaciente = Role::create(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente@example.com',
            'status' => 'active',
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $response = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => ' 12345 67890 ',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('existe', true)
            ->assertJsonPath('paciente.id', $paciente->id);
    }

    public function test_verificar_paciente_solo_valida_correo_si_fue_enviado(): void
    {
        $rolPaciente = Role::create(['name' => 'paciente']);

        $paciente = User::factory()->create([
            'dni' => '1234567890',
            'email' => 'paciente@example.com',
            'status' => 'active',
        ]);
        $paciente->roles()->attach($rolPaciente->id);

        $response = $this->postJson(route('chatbot.verificarPaciente'), [
            'cedula' => '1234567890',
            'email' => 'otro@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('existe', true);
    }
}


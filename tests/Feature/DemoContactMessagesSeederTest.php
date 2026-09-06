<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoContactMessagesSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DemoContactMessagesSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.mode' => 'demo']);
    }

    /**
     * TEST 1: El seeder DemoContactMessagesSeeder puebla entre 3 y 5 mensajes coherentes y sin PII.
     */
    public function test_demo_contact_messages_seeder_populates_realistic_dataset(): void
    {
        $this->seed(DemoContactMessagesSeeder::class);

        $messages = ContactMessage::all();

        $this->assertGreaterThanOrEqual(3, $messages->count(), 'Debe haber al menos 3 mensajes demo.');
        $this->assertLessThanOrEqual(5, $messages->count(), 'No debe exceder de 5 mensajes demo.');

        $validStatuses = ['nuevo', 'leido'];
        $hasNuevo = false;
        $hasLeido = false;

        foreach ($messages as $msg) {
            $this->assertNotEmpty($msg->nombre);
            $this->assertNotEmpty($msg->asunto);
            $this->assertNotEmpty($msg->mensaje);
            $this->assertContains($msg->estado, $validStatuses);

            // Verificar dominio de correo seguro y ausencia de PII
            $this->assertMatchesRegularExpression('/@(contacto-demo|demo-clinigest)\.test$/', $msg->correo);
            $this->assertStringNotContainsString('@gmail.com', $msg->correo);
            $this->assertStringNotContainsString('@hotmail.com', $msg->correo);
            $this->assertStringNotContainsStringIgnoringCase('josthyn', $msg->nombre);
            $this->assertStringNotContainsStringIgnoringCase('arroyo', $msg->nombre);

            // Teléfono ficticio con formato de 10 dígitos
            if ($msg->telefono) {
                $this->assertMatchesRegularExpression('/^09\d{8}$/', $msg->telefono);
            }

            if ($msg->estado === 'nuevo') {
                $hasNuevo = true;
            }
            if ($msg->estado === 'leido') {
                $hasLeido = true;
            }
        }

        $this->assertTrue($hasNuevo, 'El dataset demo debe incluir al menos un mensaje con estado nuevo.');
        $this->assertTrue($hasLeido, 'El dataset demo debe incluir al menos un mensaje con estado leído.');
    }

    /**
     * TEST 2: El seeder es idempotente y no duplica registros al ejecutarse múltiples veces.
     */
    public function test_demo_contact_messages_seeder_is_idempotent(): void
    {
        $this->seed(DemoContactMessagesSeeder::class);
        $firstCount = ContactMessage::count();

        $this->seed(DemoContactMessagesSeeder::class);
        $secondCount = ContactMessage::count();

        $this->assertSame($firstCount, $secondCount, 'Múltiples ejecuciones del seeder no deben duplicar mensajes.');
    }

    /**
     * TEST 3: La administradora demo puede consultar el listado y detalle de los mensajes en /admin/contacto/mensajes.
     */
    public function test_admin_can_view_contact_messages_index_and_detail(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = User::where('email', 'admin@demo-clinigest.test')->firstOrFail();

        // 1. Index
        $response = $this->actingAs($admin)->get(route('admin.contacto.mensajes'));

        $response->assertOk();
        $response->assertSee('Mensajes de contacto');
        $response->assertDontSee('No hay mensajes de contacto registrados.');

        $latestMessage = ContactMessage::latest()->firstOrFail();
        $response->assertSee($latestMessage->nombre);
        $response->assertSee($latestMessage->correo);

        // 2. Show
        $showResponse = $this->actingAs($admin)->get(route('admin.contacto.mensajes.show', $latestMessage));

        $showResponse->assertOk();
        $showResponse->assertSee($latestMessage->nombre);
        $showResponse->assertSee($latestMessage->mensaje);
    }
}

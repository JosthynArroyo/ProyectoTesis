<?php

namespace Tests\Unit;

use App\Jobs\EnviarConfirmacionCitaJob;
use App\Mail\CambioEstadoCitaMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarConfirmacionCitaJobUnitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_envia_correo_de_confirmacion(): void
    {
        Mail::fake();

        $paciente = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $doctor = User::factory()->create([
            'name' => 'Dr. Smith',
            'email' => 'drsmith@example.com',
        ]);
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $doctor->id,
            'especialidad_id' => $especialidad->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00:00',
        ]);

        $job = new EnviarConfirmacionCitaJob($cita);
        $job->handle();

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($paciente) {
            return $mail->hasTo($paciente->email)
                && $mail->rolReceptor === 'paciente'
                && $mail->evento === 'agendada';
        });

        Mail::assertQueued(CambioEstadoCitaMail::class, function ($mail) use ($doctor) {
            return $mail->hasTo($doctor->email)
                && $mail->rolReceptor === 'doctor'
                && $mail->evento === 'agendada';
        });
    }
}

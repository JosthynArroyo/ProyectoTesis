<?php

namespace Tests\Feature;

use App\Jobs\EnviarConfirmacionCitaJob;
use App\Mail\ConfirmacionCitaMail;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarConfirmacionCitaJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_correo_de_confirmacion_al_paciente()
    {
        Mail::fake();

        $paciente = User::factory()->create();
        $doctor = User::factory()->create();
        $especialidad = Especialidad::factory()->create();

        $cita = Cita::factory()->create([
            'paciente_id'     => $paciente->id,
            'doctor_id'       => $doctor->id,
            'especialidad_id' => $especialidad->id,
        ]);

        (new EnviarConfirmacionCitaJob($cita))->handle();

        Mail::assertSent(ConfirmacionCitaMail::class, function ($mail) use ($paciente, $doctor) {
            return $mail->hasTo($paciente->email) && $mail->hasCc($doctor->email);
        });
    }
}

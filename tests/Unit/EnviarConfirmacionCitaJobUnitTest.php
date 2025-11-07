<?php
namespace Tests\Unit;
use App\Jobs\EnviarConfirmacionCitaJob;
use App\Mail\ConfirmacionCitaMail;
use App\Models\Cita;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
class EnviarConfirmacionCitaJobUnitTest extends TestCase
{
    public function test_envia_correo_de_confirmacion(): void
    {
        Mail::fake();
        $paciente = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
        $doctor   = new User(['name' => 'Dr. Smith', 'email' => 'drsmith@example.com']);
        $cita = new Cita([
            'fecha' => now(),
            'hora'  => '10:00:00',
        ]);
        $cita->setRelation('paciente', $paciente);
        $cita->setRelation('doctor', $doctor);
        $job = new EnviarConfirmacionCitaJob($cita);
        $job->handle();
        Mail::assertSent(ConfirmacionCitaMail::class, function ($mail) use ($paciente, $doctor) {
            return $mail->hasTo($paciente->email) && $mail->hasCc($doctor->email);
        });
    }
}
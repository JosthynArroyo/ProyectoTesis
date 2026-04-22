<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomResetPasswordNotificationTest extends TestCase
{
    public function test_envia_notificacion_personalizada_de_recuperacion(): void
    {
        Notification::fake();

        $user = new User([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
        ]);

        $user->sendPasswordResetNotification('token-seguro-123');

        Notification::assertSentTo($user, CustomResetPasswordNotification::class, function ($notification, array $channels) use ($user) {
            $mail = $notification->toMail($user);
            $html = (string) $mail->render();

            $this->assertSame(['mail'], $channels);
            $this->assertSame('Recuperación de contraseña - Clínica Don Bosco', $mail->subject);
            $this->assertSame('emails.auth.password_reset', $mail->view);
            $this->assertSame(60, $mail->viewData['expireMinutes']);
            $this->assertStringContainsString('token-seguro-123', $mail->viewData['resetUrl']);
            $this->assertStringContainsString('Restablece tu contraseña', $html);

            return true;
        });
    }
}

<?php

use App\Http\Controllers\Captcha\CaptchaController;
use App\Http\Controllers\ChatBotController;
use Illuminate\Support\Facades\Route;

// =========================== CHATBOT ===========================
// 1. CAPTCHA public endpoints (with strict throttles)
Route::middleware('throttle:captcha.challenge')->group(function () {
    Route::get('/captcha/challenge', [CaptchaController::class, 'challenge'])
        ->name('captcha.challenge');
});
Route::middleware('throttle:captcha.verify')->group(function () {
    Route::post('/captcha/verify', [CaptchaController::class, 'verify'])
        ->name('captcha.verify');
});
Route::get('/captcha/challenge/{token}/image/{position}', [CaptchaController::class, 'image'])
    ->name('captcha.image.show');

// 2. Chatbot Identification endpoints (protected by CAPTCHA, throttle general or OTP)
Route::middleware(['captcha_verified'])->group(function () {
    Route::post('/chatbot/verificar-paciente', [ChatBotController::class, 'verificarPaciente'])
        ->middleware('throttle:chatbot.message')
        ->name('chatbot.verificarPaciente');

    Route::post('/chatbot/enviar-codigo', [ChatBotController::class, 'enviarCodigoVerificacion'])
        ->name('chatbot.enviarCodigo');

    Route::post('/chatbot/verificar-codigo', [ChatBotController::class, 'verificarCodigo'])
        ->middleware('throttle:chatbot.otp.verify')
        ->name('chatbot.verificarCodigo');

    Route::post('/chatbot/registrar-usuario', [ChatBotController::class, 'registrarUsuario'])
        ->middleware('throttle:chatbot.message')
        ->name('chatbot.registrarUsuario');
});

// Session cleanup (widget reset)
Route::post('/chatbot/finalizar', [ChatBotController::class, 'finalizar'])
    ->name('chatbot.finalizar');

// 3. Chatbot Transactional/Patient endpoints (protected by Identity OTP, throttle message)
Route::middleware(['chatbot_identity', 'throttle:chatbot.message'])->group(function () {
    Route::post('/chatbot/agendar', [ChatBotController::class, 'agendar'])->name('chatbot.agendar');
    Route::get('/chatbot/especialidades', [ChatBotController::class, 'especialidades'])->name('chatbot.especialidades');
    Route::get('/chatbot/especialidades/{especialidad}/doctores', [ChatBotController::class, 'doctoresPorEspecialidad'])
        ->whereNumber('especialidad')
        ->name('chatbot.especialidad.doctores');
    Route::get('/chatbot/doctores/{doctor}/fechas', [ChatBotController::class, 'fechasDisponibles'])
        ->whereNumber('doctor')
        ->name('chatbot.doctor.fechas');
    Route::post('/chatbot/buscar-citas', [ChatBotController::class, 'buscarCitas'])->name('chatbot.buscarCitas');
    Route::post('/chatbot/cancelar', [ChatBotController::class, 'cancelar'])->name('chatbot.cancelar');
    Route::post('/chatbot/reagendar', [ChatBotController::class, 'reagendar'])->name('chatbot.reagendar');
    Route::post('/chatbot/perfil', [ChatBotController::class, 'perfil'])->name('chatbot.perfil');
    Route::post('/chatbot/perfil/actualizar', [ChatBotController::class, 'actualizarPerfil'])->name('chatbot.perfil.actualizar');
});

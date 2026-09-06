<?php

use App\Http\Controllers\Admin\AdminController as AdminDashboardController;
use App\Http\Controllers\Api\DoctorSlotController;
use App\Http\Controllers\Api\SlotHoldController;
use App\Http\Controllers\Api\TarifaController;
use App\Http\Controllers\Auth\MustChangePasswordController;
use App\Http\Controllers\AvatarMediaController;
use App\Http\Controllers\CitaComprobanteController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\DependentAvatarMediaController;
use App\Http\Controllers\DocumentoVerificacionController;
use App\Http\Controllers\EmailCitaActionController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\PagoLookupController;
use App\Http\Controllers\PanelThemeController;
use App\Http\Controllers\PaymentReceiptVerificationController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Dynamic favicon endpoint (Root favicon)
Route::get('/favicon.ico', FaviconController::class)->name('favicon.ico');

// Private avatar endpoint
Route::middleware(['auth'])->get('/media/avatars/{user}/{variant?}', AvatarMediaController::class)
    ->where('variant', 'thumb|medium|original')
    ->name('media.avatars.show');

// Private dependent avatar endpoint
Route::middleware(['auth'])->get('/media/dependent-avatars/{dependiente}/{variant?}', DependentAvatarMediaController::class)
    ->where('variant', 'thumb|medium|original')
    ->name('media.dependent-avatars.show');

// API tarifas
Route::get('/api/tarifa/doctor/{id}', [TarifaController::class, 'precioDoctor'])
    ->whereNumber('id')->name('api.tarifa.doctor.show');

// API slots disponibles
Route::get('/api/doctor/{doctor}/fecha/{fecha}/slots', DoctorSlotController::class)
    ->whereNumber('doctor')->where('fecha', '\d{4}-\d{2}-\d{2}')
    ->name('api.doctor.slots');

Route::post('/api/slot-holds', [SlotHoldController::class, 'store'])
    ->middleware('throttle:slot-holds')
    ->name('api.slot-holds.store');

// Página principal
Route::get('/', [PublicPageController::class, 'welcome'])->name('home.index');

// Auth
Auth::routes(['register' => false]);

// Doctores por especialidad (pública)
Route::get('/especialidades/{especialidad}/doctores', [AdminDashboardController::class, 'doctoresPorEspecialidad'])
    ->name('especialidades.doctores');

// Home según rol
Route::get('/home', [PublicPageController::class, 'home'])->name('home');

// Contacto
Route::get('/contacto', [ContactoController::class, 'mostrarFormulario'])->name('contacto.form');
Route::post('/contacto', [ContactoController::class, 'storeContacto'])
    ->middleware('throttle:contacto')   // usa el limiter definido
    ->name('contacto.enviar');

// Servicios
Route::get('/servicios', [PublicPageController::class, 'servicios'])->name('servicios.index');

Route::get('/verificar-documento', [DocumentoVerificacionController::class, 'create'])
    ->name('documentos.verificar.form');
Route::post('/verificar-documento', [DocumentoVerificacionController::class, 'search'])
    ->name('documentos.verificar.search');
Route::get('/verificar/{csv}', [DocumentoVerificacionController::class, 'show'])
    ->where('csv', '[A-Za-z0-9\-]+')
    ->name('documentos.verificar.show');
Route::get('/verificar-recibo/{token}', [PaymentReceiptVerificationController::class, 'show'])
    ->name('recibos.verificar');
Route::get('/cobro/{token}', [PagoLookupController::class, 'showByToken'])
    ->name('pagos.token.show');

// Rutas firmadas por email
Route::middleware('signed')->get('/email/cita/{cita}/{rol}/{accion}', EmailCitaActionController::class)
    ->where('rol', '^(paciente|doctor)$')->where('accion', '^(aceptar|cancelar)$')
    ->name('email.cita.action');

// Cita comprobante
Route::get('/cita/comprobante/{token}', [CitaComprobanteController::class, 'showByToken'])
    ->name('citas.comprobante.show');

// Logout
Route::post('/salir', [PublicPageController::class, 'logout'])->name('salir');

// Rutas autenticadas de tema y contraseña obligatoria
Route::middleware('auth')->group(function () {
    Route::patch('/panel/theme', [PanelThemeController::class, 'update'])->name('panel.theme.update');
    Route::get('/auth/must-change-password', [MustChangePasswordController::class, 'show'])->name('auth.must-change-password');
    Route::post('/auth/must-change-password', [MustChangePasswordController::class, 'update'])->name('auth.must-change-password.update');
});

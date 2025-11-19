<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\Admin\AdminController as AdminDashboardController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\ExportCitasController;
use App\Http\Controllers\Paciente\AdminController as PacienteDashboardController;
use App\Http\Controllers\Doctor\AdminController as DoctorDashboardController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\Api\TarifaController;
use App\Http\Controllers\Doctor\RecetaController;
use App\Http\Controllers\EmailCitaActionController;
use App\Http\Controllers\Api\DoctorSlotController;
use App\Http\Controllers\Doctor\HorarioController as DoctorHorarioController;
use App\Models\User;
use App\Http\Controllers\ChatBotController;
use App\Http\Controllers\FaceAuthController;


// API tarifas
Route::get('/api/tarifa/doctor/{id}', [TarifaController::class, 'precioDoctor'])
    ->whereNumber('id')->name('api.tarifa.doctor.show');

// API slots disponibles
Route::get('/api/doctor/{doctor}/fecha/{fecha}/slots', DoctorSlotController::class)
    ->whereNumber('doctor')->where('fecha','\d{4}-\d{2}-\d{2}')
    ->name('api.doctor.slots');

// Página principal
Route::get('/', fn() => view('welcome'));

// Auth
Auth::routes(['register' => false]);

// Doctores por especialidad (pública)
Route::get('/especialidades/{especialidad}/doctores', [AdminDashboardController::class, 'doctoresPorEspecialidad'])
    ->name('especialidades.doctores');

// Home según rol
Route::get('/home', function () {
    if (!Auth::check()) return redirect('/');
    $u = Auth::user();
    if ($u->hasRole('administrador')) return redirect()->route('admin.dashboard');
    if ($u->hasRole('paciente')) return redirect()->route('paciente.dashboard');
    if ($u->hasRole('doctor')) return redirect()->route('doctor.dashboard');
    return redirect('/');
})->name('home');

// Contacto
Route::get('/contacto', [ContactoController::class, 'mostrarFormulario'])->name('contacto.form');
Route::post('/contacto', [ContactoController::class, 'enviarFormulario'])
    ->middleware('throttle:contacto')   // usa el limiter definido
    ->name('contacto.enviar');

// Servicios
Route::get('/servicios', fn() => view('servicios'))->name('servicios.index');

// Rutas firmadas por email
Route::middleware('signed')->get('/email/cita/{cita}/{rol}/{accion}', EmailCitaActionController::class)
    ->where('rol', '^(paciente|doctor)$')->where('accion', '^(aceptar|cancelar)$')
    ->name('email.cita.action');

// =========================== ADMIN ===========================
Route::middleware(['auth', 'role:administrador'])
    ->prefix('admin')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/resumen', [AdminDashboardController::class, 'resumenGlobal'])->name('dashboard.resumen');

    // Perfil
    Route::get('/perfil', [AdminDashboardController::class, 'editarPerfil'])->name('perfil.edit');
    Route::post('/perfil', [AdminDashboardController::class, 'actualizarPerfil'])->name('perfil.update');

    // Usuarios
    Route::get('/usuarios', [AdminDashboardController::class, 'usuarios'])->name('usuarios.index');
    Route::get('/usuarios/crear', [AdminDashboardController::class, 'usuariosCreate'])->name('usuarios.create');
    Route::post('/usuarios', [AdminDashboardController::class, 'usuariosStore'])->name('usuarios.store');
    Route::get('/usuarios/{user}', [AdminDashboardController::class, 'usuariosShow'])->name('usuarios.show');
    Route::get('/usuarios/{user}/editar', [AdminDashboardController::class, 'usuariosEdit'])->name('usuarios.edit');
    Route::put('/usuarios/{user}', [AdminDashboardController::class, 'usuariosUpdate'])->name('usuarios.update');
    Route::delete('/usuarios/{user}', [AdminDashboardController::class, 'usuariosDestroy'])->name('usuarios.destroy');

    // Exportes usuarios
    Route::get('/usuarios/export/excel', [AdminDashboardController::class, 'usuariosExportExcel'])->name('usuarios.export.excel');
    Route::get('/usuarios/export/pdf',   [AdminDashboardController::class, 'usuariosExportPdf'])->name('usuarios.export.pdf');

    // Estado de cuenta
    Route::patch('/usuarios/{user}/block', function(User $user){
        $user->update(['status' => 'blocked', 'suspended_until' => null, 'deactivation_reason' => request('reason')]);
        return back()->with('success','Usuario bloqueado');
    })->name('usuarios.block');

    Route::patch('/usuarios/{user}/suspend', function(User $user){
        $user->update(['status' => 'active', 'suspended_until' => request('until'), 'deactivation_reason' => request('reason')]);
        return back()->with('success','Usuario suspendido temporalmente');
    })->name('usuarios.suspend');

    Route::patch('/usuarios/{user}/activate', function(User $user){
        $user->update(['status' => 'active', 'suspended_until' => null, 'deactivation_reason' => null]);
        return back()->with('success','Usuario reactivado');
    })->name('usuarios.activate');

    Route::patch('/usuarios/{user}/deactivate', function(User $user){
        $user->update(['status' => 'inactive', 'suspended_until' => null, 'deactivation_reason' => request('reason')]);
        return back()->with('success','Usuario marcado como inactivo');
    })->name('usuarios.deactivate');

    // Exportar citas
    Route::get('/citas/export', [ExportCitasController::class, 'exportarCitas'])->name('citas.export');

    // Horarios
    Route::get('/horarios', [HorarioController::class, 'index'])->name('horarios.index');
    Route::get('/horarios/crear', [HorarioController::class, 'create'])->name('horarios.create');
    Route::post('/horarios', [HorarioController::class, 'store'])->name('horarios.store');
    Route::get('/horarios/{horario}/editar', [HorarioController::class, 'edit'])->name('horarios.edit');
    Route::put('/horarios/{horario}', [HorarioController::class, 'update'])->name('horarios.update');
    Route::delete('/horarios/{horario}', [HorarioController::class, 'destroy'])->name('horarios.destroy');

    // Auditoría de cambios de citas
    Route::get('/cambios-citas', [\App\Http\Controllers\Admin\CitaEventosController::class,'index'])->name('cambios-citas.index');
    Route::get('/cambios-citas/export/excel', [\App\Http\Controllers\Admin\CitaEventosController::class,'exportExcel'])->name('cambios-citas.export.excel');
    Route::get('/cambios-citas/export/pdf',   [\App\Http\Controllers\Admin\CitaEventosController::class,'exportPdf'])->name('cambios-citas.export.pdf');
});

// ========================== PACIENTE =========================
Route::middleware(['auth', 'role:paciente'])->prefix('paciente')->group(function () {
    Route::get('/dashboard', [PacienteDashboardController::class, 'dashboard'])->name('paciente.dashboard');
    Route::get('/perfil', [PacienteDashboardController::class, 'editarPerfil'])->name('paciente.perfil.edit');
    Route::post('/perfil', [PacienteDashboardController::class, 'actualizarPerfil'])->name('paciente.perfil.update');
    Route::get('/citas', [CitaController::class, 'index'])->name('paciente.citas');
    Route::get('/crear-cita', [CitaController::class, 'create'])->name('paciente.crear-cita');
    Route::post('/crear-cita', [CitaController::class, 'store'])->name('paciente.crear-cita.store');
    Route::post('/citas/{id}/cancelar', [CitaController::class, 'cancelar'])->name('paciente.citas.cancelar');
    Route::get('/editar-cita/{id}', [CitaController::class, 'edit'])->name('paciente.editar-cita');
    Route::put('/editar-cita/{id}', [CitaController::class, 'actualizar'])->name('paciente.editar-cita.update');
    Route::view('/historial', 'paciente.historial')->name('paciente.historial');
    Route::view('/mensajes', 'paciente.mensajes')->name('paciente.mensajes');
});

// =========================== DOCTOR ==========================
Route::middleware(['auth', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::get('/dashboard', [DoctorDashboardController::class, 'dashboard'])->name('doctor.dashboard');
    Route::get('/dashboard/data', [DoctorDashboardController::class, 'dashboardData'])->name('doctor.dashboard.data');
    Route::get('/perfil', [DoctorDashboardController::class, 'editarPerfil'])->name('doctor.perfil.edit');
    Route::post('/perfil', [DoctorDashboardController::class, 'actualizarPerfil'])->name('doctor.perfil.update');
    Route::get('/citas', [CitaController::class, 'indexDoctor'])->name('doctor.citas');
    Route::post('/citas/{id}/aceptar', [CitaController::class, 'aceptar'])->name('doctor.citas.aceptar');
    Route::post('/citas/{id}/rechazar', [CitaController::class, 'rechazar'])->name('doctor.citas.rechazar');
    Route::post('/citas/{id}/realizar', [CitaController::class, 'realizar'])->name('doctor.citas.realizar');
    Route::get('/agenda', [DoctorDashboardController::class, 'agenda'])->name('doctor.agenda');

    Route::get('/disponibilidad/check', [CitaController::class,'checkDisponibilidad'])
        ->name('doctor.disponibilidad.check');

    // Crear “próxima cita” desde una cita realizada
    Route::post('/citas/{cita}/proxima', [CitaController::class,'proximaDesdeCita'])
        ->whereNumber('cita')->name('doctor.citas.proxima');

    // Crear próxima cita con fecha/hora elegidas en el toast
    Route::post('/citas/{cita}/proxima/planificar', [CitaController::class,'proximaPlanificada'])
        ->whereNumber('cita')->name('doctor.citas.proxima.planificada');

    // Recetas
    Route::get('/recetas', [RecetaController::class, 'index'])->name('doctor.recetas.index');
    Route::get('/recetas/crear/{cita}', [RecetaController::class, 'create'])->name('doctor.recetas.create');
    Route::post('/recetas', [RecetaController::class, 'store'])->name('doctor.recetas.store');
    Route::get('/recetas/editar/{cita}', [RecetaController::class, 'edit'])->name('doctor.recetas.edit');
    Route::post('/recetas/actualizar', [RecetaController::class, 'update'])->name('doctor.recetas.update');
    Route::post('/recetas/reenviar/{cita}', [RecetaController::class, 'resend'])->name('doctor.recetas.resend');
    Route::get('/recetas/descargar/{cita}', [RecetaController::class, 'download'])->name('doctor.recetas.download');

    // Horarios
    Route::get('/horario',                [DoctorHorarioController::class,'index'])->name('doctor.horario.index');
    Route::post('/horario',               [DoctorHorarioController::class,'store'])->name('doctor.horario.store');
    Route::get('/horario/{horario}/edit', [DoctorHorarioController::class,'edit'])->name('doctor.horario.edit');
    Route::put('/horario/{horario}',      [DoctorHorarioController::class,'update'])->name('doctor.horario.update');
    Route::delete('/horario/{horario}',   [DoctorHorarioController::class,'destroy'])->name('doctor.horario.destroy');
    Route::post('/horario/generar',       [DoctorHorarioController::class,'generarRango'])->name('doctor.horario.generar');
});

// =========================== LOGOUT ==========================
Route::post('/salir', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->name('salir');

Route::get('/salir', function (Request $request) {
    if ($request->user()) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
    return redirect('/');
})->name('salir.get');

// ============================ LOGIN ==========================
Route::get('/login', function () {
    session()->reflash();
    return redirect('/?login=1');
})->name('login');


// =========================== CHATBOT ===========================
Route::get('/chatbot/especialidades/{especialidad}/doctores', [ChatBotController::class, 'doctoresPorEspecialidad'])
    ->whereNumber('especialidad')
    ->name('chatbot.especialidad.doctores');

Route::get('/chatbot/doctores/{doctor}/fechas', [ChatBotController::class, 'fechasDisponibles'])
    ->whereNumber('doctor')
    ->name('chatbot.doctor.fechas');

Route::get('/asistente', [ChatBotController::class, 'index'])->name('chatbot.index');
Route::get('/chatbot/especialidades', [ChatBotController::class, 'especialidades'])->name('chatbot.especialidades');
Route::post('/chatbot/verificar-paciente', [ChatBotController::class, 'verificarPaciente'])->name('chatbot.verificarPaciente');
Route::post('/chatbot/agendar', [ChatBotController::class, 'agendar'])->name('chatbot.agendar');
Route::post('/chatbot/buscar-citas', [ChatBotController::class, 'buscarCitas'])->name('chatbot.buscarCitas');
Route::post('/chatbot/cancelar', [ChatBotController::class, 'cancelar'])->name('chatbot.cancelar');
Route::post('/chatbot/reagendar', [ChatBotController::class, 'reagendar'])->name('chatbot.reagendar');


Route::middleware('auth')->group(function () {
    Route::get('/face/enroll', [FaceAuthController::class, 'showEnrollment'])->name('face.enroll');
    Route::post('/face/enroll', [FaceAuthController::class, 'storeEnrollment']);
});

Route::middleware('guest')->group(function () {
    Route::post('/face/login', [FaceAuthController::class, 'verifyLogin'])->name('face.login');
});
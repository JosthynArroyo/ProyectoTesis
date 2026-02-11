<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\Admin\AdminController as AdminDashboardController;
use App\Http\Controllers\Admin\PersonalizacionController as AdminPersonalizacionController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\ExportCitasController;
use App\Http\Controllers\Paciente\AdminController as PacienteDashboardController;
use App\Http\Controllers\Doctor\AdminController as DoctorDashboardController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\Api\TarifaController;
use App\Http\Controllers\Doctor\RecetaController;
use App\Http\Controllers\Doctor\SoapController as DoctorSoapController;
use App\Http\Controllers\Doctor\HistorialController as DoctorHistorialController;
use App\Http\Controllers\EmailCitaActionController;
use App\Http\Controllers\Api\DoctorSlotController;
use App\Http\Controllers\Doctor\HorarioController as DoctorHorarioController;
use App\Http\Controllers\Doctor\LaboratorioController as DoctorLaboratorioController;
use App\Http\Controllers\Laboratorio\AdminController as LaboratorioDashboardController;
use App\Http\Controllers\Laboratorio\OrdenController as LaboratorioOrdenController;
use App\Models\User;
use App\Models\Especialidad;
use App\Http\Controllers\ChatBotController;
use App\Http\Controllers\FaceAuthController;
use App\Http\Controllers\Paciente\LaboratorioController as PacienteLaboratorioController;
use App\Http\Controllers\Paciente\LabOrderController as PacienteLabOrderController;
use App\Http\Controllers\Paciente\HistorialController as PacienteHistorialController;
use App\Http\Controllers\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\Superadmin\AdminsController as SuperadminAdminsController;
use App\Http\Controllers\Superadmin\PersonalizacionController as SuperadminPersonalizacionController;
use App\Http\Controllers\Superadmin\PersonalizacionRequestController as SuperadminPersonalizacionRequestController;
use App\Http\Controllers\Superadmin\MaintenanceController as SuperadminMaintenanceController;
use App\Http\Controllers\Admin\HistorialController as AdminHistorialController;


// API tarifas
Route::get('/api/tarifa/doctor/{id}', [TarifaController::class, 'precioDoctor'])
    ->whereNumber('id')->name('api.tarifa.doctor.show');

// API slots disponibles
Route::get('/api/doctor/{doctor}/fecha/{fecha}/slots', DoctorSlotController::class)
    ->whereNumber('doctor')->where('fecha','\d{4}-\d{2}-\d{2}')
    ->name('api.doctor.slots');

// Página principal
Route::get('/', function () {
    $welcome = app(\App\Services\LandingWelcomeService::class);
    $featured = $welcome->featuredSpecialties();

    $especialidadesDestacadas = !empty($featured)
        ? collect($featured)
        : \App\Models\Especialidad::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

    return view('welcome', compact('especialidadesDestacadas'));
});

// Auth
Auth::routes(['register' => false]);

// Doctores por especialidad (pública)
Route::get('/especialidades/{especialidad}/doctores', [AdminDashboardController::class, 'doctoresPorEspecialidad'])
    ->name('especialidades.doctores');

// Home según rol
Route::get('/home', function () {
    if (!Auth::check()) return redirect('/');
    $u = Auth::user();
    if ($u->hasRole('superadmin')) return redirect()->route('superadmin.dashboard');
    if ($u->hasRole('administrador')) return redirect()->route('admin.dashboard');
    if ($u->hasRole('paciente')) return redirect()->route('paciente.dashboard');
    if ($u->hasRole('doctor')) return redirect()->route('doctor.dashboard');
    if ($u->hasRole('laboratorio')) return redirect()->route('laboratorio.dashboard');
    return redirect('/');
})->name('home');

// Contacto
Route::get('/contacto', [ContactoController::class, 'mostrarFormulario'])->name('contacto.form');
Route::post('/contacto', [ContactoController::class, 'enviarFormulario'])
    ->middleware('throttle:contacto')   // usa el limiter definido
    ->name('contacto.enviar');

// Servicios
Route::get('/servicios', function () {
    $especialidades = Especialidad::query()
        ->where('activo', true)
        ->orderBy('orden')
        ->orderBy('nombre')
        ->get();
    return view('servicios', compact('especialidades'));
})->name('servicios.index');

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

    // Personalizacion (requiere aprobacion)
    Route::get('/personalizacion', function () {
        return redirect()->route('admin.personalizacion.bienvenida.edit');
    })->middleware('feature:personalizacion');
    Route::get('/personalizacion/bienvenida', [AdminPersonalizacionController::class, 'edit'])
        ->middleware('feature:personalizacion')
        ->name('personalizacion.bienvenida.edit');
    Route::put('/personalizacion/bienvenida', [AdminPersonalizacionController::class, 'update'])
        ->middleware('feature:personalizacion')
        ->name('personalizacion.bienvenida.update');
    Route::get('/personalizacion/servicios', [AdminPersonalizacionController::class, 'serviciosEdit'])
        ->middleware('feature:personalizacion')
        ->name('personalizacion.servicios.edit');
    Route::put('/personalizacion/servicios', [AdminPersonalizacionController::class, 'serviciosUpdate'])
        ->middleware('feature:personalizacion')
        ->name('personalizacion.servicios.update');
    Route::post('/personalizacion/solicitar', [AdminPersonalizacionController::class, 'requestAccess'])
        ->name('personalizacion.request');

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
        if ($user->hasRole('administrador') || $user->hasRole('superadmin')) {
            return back()->withErrors(['No puedes bloquear cuentas Administrador o Superadmin.']);
        }
        $user->update(['status' => 'blocked', 'suspended_until' => null, 'deactivation_reason' => request('reason')]);
        return back()->with('success','Usuario bloqueado');
    })->name('usuarios.block');

    Route::patch('/usuarios/{user}/suspend', function(User $user){
        if ($user->hasRole('administrador') || $user->hasRole('superadmin')) {
            return back()->withErrors(['No puedes suspender cuentas Administrador o Superadmin.']);
        }
        $user->update(['status' => 'active', 'suspended_until' => request('until'), 'deactivation_reason' => request('reason')]);
        return back()->with('success','Usuario suspendido temporalmente');
    })->name('usuarios.suspend');

    Route::patch('/usuarios/{user}/activate', function(User $user){
        if ($user->hasRole('administrador') || $user->hasRole('superadmin')) {
            return back()->withErrors(['No puedes reactivar cuentas Administrador o Superadmin.']);
        }
        $user->update(['status' => 'active', 'suspended_until' => null, 'deactivation_reason' => null]);
        return back()->with('success','Usuario reactivado');
    })->name('usuarios.activate');

    Route::patch('/usuarios/{user}/deactivate', function(User $user){
        if ($user->hasRole('administrador') || $user->hasRole('superadmin')) {
            return back()->withErrors(['No puedes desactivar cuentas Administrador o Superadmin.']);
        }
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

    // Historial clínico (solo lectura)
    Route::get('/historial', [AdminHistorialController::class, 'index'])->name('historial.index');
    Route::get('/historial/paciente/{paciente}', [AdminHistorialController::class, 'paciente'])->name('historial.paciente');
    Route::get('/historial/{nota}', [AdminHistorialController::class, 'show'])->name('historial.show');
});

// ======================== SUPERADMIN ========================
Route::middleware(['auth', 'role:superadmin'])
    ->prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/dashboard', [SuperadminDashboardController::class, 'index'])->name('dashboard');

    // Administradores
    Route::get('/admins', [SuperadminAdminsController::class, 'index'])->name('admins.index');
    Route::get('/admins/crear', [SuperadminAdminsController::class, 'create'])->name('admins.create');
    Route::post('/admins', [SuperadminAdminsController::class, 'store'])->name('admins.store');
    Route::get('/admins/{admin}/editar', [SuperadminAdminsController::class, 'edit'])->name('admins.edit');
    Route::put('/admins/{admin}', [SuperadminAdminsController::class, 'update'])->name('admins.update');
    Route::delete('/admins/{admin}', [SuperadminAdminsController::class, 'destroy'])->name('admins.destroy');
    Route::patch('/admins/{admin}/block', [SuperadminAdminsController::class, 'block'])->name('admins.block');
    Route::patch('/admins/{admin}/suspend', [SuperadminAdminsController::class, 'suspend'])->name('admins.suspend');
    Route::patch('/admins/{admin}/activate', [SuperadminAdminsController::class, 'activate'])->name('admins.activate');
    Route::patch('/admins/{admin}/deactivate', [SuperadminAdminsController::class, 'deactivate'])->name('admins.deactivate');

    // Solicitudes de personalizacion
    Route::get('/solicitudes/personalizacion', [SuperadminPersonalizacionRequestController::class, 'index'])
        ->name('solicitudes.personalizacion.index');
    Route::patch('/solicitudes/personalizacion/{accessRequest}/aprobar', [SuperadminPersonalizacionRequestController::class, 'approve'])
        ->name('solicitudes.personalizacion.aprobar');
    Route::patch('/solicitudes/personalizacion/{accessRequest}/rechazar', [SuperadminPersonalizacionRequestController::class, 'reject'])
        ->name('solicitudes.personalizacion.rechazar');
    Route::patch('/solicitudes/personalizacion/{accessRequest}/revocar', [SuperadminPersonalizacionRequestController::class, 'revoke'])
        ->name('solicitudes.personalizacion.revocar');

    // Personalizacion
    Route::get('/personalizacion', function () {
        return redirect()->route('superadmin.personalizacion.bienvenida.edit');
    });
    Route::get('/personalizacion/bienvenida', [SuperadminPersonalizacionController::class, 'edit'])
        ->name('personalizacion.bienvenida.edit');
    Route::put('/personalizacion/bienvenida', [SuperadminPersonalizacionController::class, 'update'])
        ->name('personalizacion.bienvenida.update');
    Route::get('/personalizacion/servicios', [SuperadminPersonalizacionController::class, 'serviciosEdit'])
        ->name('personalizacion.servicios.edit');
    Route::put('/personalizacion/servicios', [SuperadminPersonalizacionController::class, 'serviciosUpdate'])
        ->name('personalizacion.servicios.update');

    // Mantenimiento
    Route::get('/mantenimiento', [SuperadminMaintenanceController::class, 'edit'])
        ->name('maintenance.edit');
    Route::put('/mantenimiento', [SuperadminMaintenanceController::class, 'update'])
        ->name('maintenance.update');
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
    Route::get('/laboratorio', [PacienteLaboratorioController::class, 'index'])->name('paciente.laboratorio.index');
    Route::get('/laboratorio/{orden}/descargar', [PacienteLaboratorioController::class, 'download'])
        ->whereNumber('orden')->name('paciente.laboratorio.download');
    Route::get('/laboratorio/solicitar', [PacienteLabOrderController::class, 'create'])
        ->name('paciente.laboratorio.solicitar');
    Route::post('/laboratorio/solicitar', [PacienteLabOrderController::class, 'store'])
        ->name('paciente.laboratorio.solicitar.store');
    Route::get('/historial', [PacienteHistorialController::class, 'index'])->name('paciente.historial');
    Route::get('/historial/{nota}', [PacienteHistorialController::class, 'show'])->name('paciente.historial.show');
    Route::view('/mensajes', 'paciente.mensajes')->name('paciente.mensajes');
});

// =========================== DOCTOR ==========================
Route::middleware(['auth', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::get('/dashboard', [DoctorDashboardController::class, 'dashboard'])->name('doctor.dashboard');
    Route::get('/dashboard/data', [DoctorDashboardController::class, 'dashboardData'])->name('doctor.dashboard.data');
    Route::get('/perfil', [DoctorDashboardController::class, 'editarPerfil'])->name('doctor.perfil.edit');
    Route::post('/perfil', [DoctorDashboardController::class, 'actualizarPerfil'])->name('doctor.perfil.update');
    Route::get('/citas', [CitaController::class, 'indexDoctor'])->name('doctor.citas');
    Route::get('/citas/export/excel', [CitaController::class, 'exportarDoctorExcel'])->name('doctor.citas.export.excel');
    Route::get('/citas/export/pdf', [CitaController::class, 'exportarDoctorPdf'])->name('doctor.citas.export.pdf');
    Route::post('/citas/{id}/aceptar', [CitaController::class, 'aceptar'])->name('doctor.citas.aceptar');
    Route::post('/citas/{id}/rechazar', [CitaController::class, 'rechazar'])->name('doctor.citas.rechazar');
    Route::post('/citas/{id}/realizar', [CitaController::class, 'realizar'])->name('doctor.citas.realizar');
    Route::get('/agenda', [DoctorDashboardController::class, 'agenda'])->name('doctor.agenda');

    Route::get('/disponibilidad/check', [CitaController::class,'checkDisponibilidad'])
        ->name('doctor.disponibilidad.check');

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

    // Orden de laboratorio (doctor)
    Route::get('/laboratorio/ordenar', [DoctorLaboratorioController::class, 'create'])->name('doctor.laboratorio.create');
    Route::post('/laboratorio', [DoctorLaboratorioController::class, 'store'])->name('doctor.laboratorio.store');

    // Nota clínica SOAP
    Route::get('/citas/{cita}/soap', [DoctorSoapController::class, 'show'])->name('doctor.citas.soap');
    Route::post('/citas/{cita}/soap', [DoctorSoapController::class, 'store'])->name('doctor.citas.soap.store');
    Route::post('/citas/{cita}/soap/firmar', [DoctorSoapController::class, 'firmar'])->name('doctor.citas.soap.firmar');
    Route::post('/citas/{cita}/soap/enmienda', [DoctorSoapController::class, 'enmienda'])->name('doctor.citas.soap.enmienda');
    Route::get('/pacientes/{paciente}/historial', [DoctorHistorialController::class, 'show'])->name('doctor.pacientes.historial');

    // Horarios
    Route::get('/horario',                [DoctorHorarioController::class,'index'])->name('doctor.horario.index');
    Route::post('/horario',               [DoctorHorarioController::class,'store'])->name('doctor.horario.store');
    Route::get('/horario/{horario}/edit', [DoctorHorarioController::class,'edit'])->name('doctor.horario.edit');
    Route::put('/horario/{horario}',      [DoctorHorarioController::class,'update'])->name('doctor.horario.update');
    Route::delete('/horario/{horario}',   [DoctorHorarioController::class,'destroy'])->name('doctor.horario.destroy');
    Route::post('/horario/generar',       [DoctorHorarioController::class,'generarRango'])->name('doctor.horario.generar');
});

// ======================== LABORATORIO =======================
Route::middleware(['auth', 'role:laboratorio'])->prefix('laboratorio')->name('laboratorio.')->group(function () {
    Route::get('/dashboard', [LaboratorioDashboardController::class, 'dashboard'])->name('dashboard');

    Route::get('/ordenes', [LaboratorioOrdenController::class, 'index'])->name('ordenes.index');
    Route::post('/ordenes/{orden}/muestra', [LaboratorioOrdenController::class, 'marcarMuestra'])
        ->whereNumber('orden')->name('ordenes.muestra');
    Route::post('/ordenes/{orden}/resultado', [LaboratorioOrdenController::class, 'subirResultado'])
        ->whereNumber('orden')->name('ordenes.resultado');
    Route::get('/ordenes/{orden}/download', [LaboratorioOrdenController::class, 'download'])
        ->whereNumber('orden')->name('ordenes.download');

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
Route::get('/asistente', [ChatBotController::class, 'index'])->name('chatbot.index');

Route::middleware('throttle:chatbot')->group(function () {
    Route::get('/chatbot/especialidades', [ChatBotController::class, 'especialidades'])->name('chatbot.especialidades');
    Route::get('/chatbot/especialidades/{especialidad}/doctores', [ChatBotController::class, 'doctoresPorEspecialidad'])
        ->whereNumber('especialidad')
        ->name('chatbot.especialidad.doctores');
    Route::get('/chatbot/doctores/{doctor}/fechas', [ChatBotController::class, 'fechasDisponibles'])
        ->whereNumber('doctor')
        ->name('chatbot.doctor.fechas');
    Route::post('/chatbot/verificar-paciente', [ChatBotController::class, 'verificarPaciente'])->name('chatbot.verificarPaciente');
    Route::post('/chatbot/enviar-codigo', [ChatBotController::class, 'enviarCodigoVerificacion'])->name('chatbot.enviarCodigo');
    Route::post('/chatbot/verificar-codigo', [ChatBotController::class, 'verificarCodigo'])->name('chatbot.verificarCodigo');
    Route::post('/chatbot/agendar', [ChatBotController::class, 'agendar'])->name('chatbot.agendar');
    Route::post('/chatbot/buscar-citas', [ChatBotController::class, 'buscarCitas'])->name('chatbot.buscarCitas');
    Route::post('/chatbot/cancelar', [ChatBotController::class, 'cancelar'])->name('chatbot.cancelar');
    Route::post('/chatbot/reagendar', [ChatBotController::class, 'reagendar'])->name('chatbot.reagendar');
    Route::post('/chatbot/perfil', [ChatBotController::class, 'perfil'])->name('chatbot.perfil');
    Route::post('/chatbot/perfil/actualizar', [ChatBotController::class, 'actualizarPerfil'])->name('chatbot.perfil.actualizar');
});

Route::middleware('auth')->group(function () {
    Route::get('/face/enroll', [FaceAuthController::class, 'showEnrollment'])->name('face.enroll');
    Route::post('/face/enroll', [FaceAuthController::class, 'storeEnrollment'])->middleware('throttle:face-enroll');
});

Route::middleware('guest')->group(function () {
    Route::post('/face/login', [FaceAuthController::class, 'verifyLogin'])->name('face.login');
});

Route::middleware('throttle:chatbot')->group(function () {
    Route::get('/captcha/challenge', [\App\Http\Controllers\Captcha\CaptchaController::class, 'challenge'])
        ->name('captcha.challenge');
    Route::post('/captcha/verify', [\App\Http\Controllers\Captcha\CaptchaController::class, 'verify'])
        ->name('captcha.verify');
});

Route::get('/captcha/image/{image}', [\App\Http\Controllers\Captcha\CaptchaController::class, 'image'])
    ->whereNumber('image')
    ->name('captcha.image.show');

<?php

use App\Http\Controllers\Admin\AdminController as AdminDashboardController;
use App\Http\Controllers\Admin\CitaOverrideController as AdminCitaOverrideController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\HistorialController as AdminHistorialController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\PersonalizacionController as AdminPersonalizacionController;
use App\Http\Controllers\Admin\RecordatorioController as AdminRecordatorioController;
use App\Http\Controllers\Admin\UserStatusController as AdminUserStatusController;
use App\Http\Controllers\Api\DoctorSlotController;
use App\Http\Controllers\Api\SlotHoldController;
use App\Http\Controllers\Api\TarifaController;
use App\Http\Controllers\ChatBotController;
use App\Http\Controllers\CitaComprobanteController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\DemoDashboardController;
use App\Http\Controllers\CitaPrioridadController;
use App\Http\Controllers\DocumentoVerificacionController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\Doctor\AdminController as DoctorDashboardController;
use App\Http\Controllers\Doctor\CertificadoMedicoController as DoctorCertificadoMedicoController;
use App\Http\Controllers\Doctor\HistorialController as DoctorHistorialController;
use App\Http\Controllers\Doctor\HorarioController as DoctorHorarioController;
use App\Http\Controllers\Doctor\RecetaController;
use App\Http\Controllers\Doctor\SoapController as DoctorSoapController;
use App\Http\Controllers\Doctor\PedidoLaboratorioController;
use App\Http\Controllers\EmailCitaActionController;
use App\Http\Controllers\ExportCitasController;

use App\Http\Controllers\Laboratorio\AdminController as LaboratorioDashboardController;
use App\Http\Controllers\Laboratorio\HorarioController as LaboratorioHorarioController;
use App\Http\Controllers\Laboratorio\OrdenController as LaboratorioOrdenController;
use App\Http\Controllers\Paciente\AdminController as PacienteDashboardController;
use App\Http\Controllers\Paciente\CertificadoMedicoController as PacienteCertificadoMedicoController;
use App\Http\Controllers\Paciente\HistorialController as PacienteHistorialController;
use App\Http\Controllers\Paciente\LaboratorioController as PacienteLaboratorioController;
use App\Http\Controllers\Paciente\PagoController as PacientePagoController;
use App\Http\Controllers\DependienteController;
use App\Http\Controllers\PagoLookupController;
use App\Http\Controllers\PanelThemeController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Superadmin\AdminsController as SuperadminAdminsController;
use App\Http\Controllers\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\Superadmin\MaintenanceController as SuperadminMaintenanceController;
use App\Http\Controllers\Superadmin\PersonalizacionController as SuperadminPersonalizacionController;
use App\Http\Controllers\Superadmin\PersonalizacionRequestController as SuperadminPersonalizacionRequestController;
use Illuminate\Support\Facades\Route;

// API tarifas
Route::get('/api/tarifa/doctor/{id}', [TarifaController::class, 'precioDoctor'])
    ->whereNumber('id')->name('api.tarifa.doctor.show');

// API slots disponibles
Route::get('/api/doctor/{doctor}/fecha/{fecha}/slots', DoctorSlotController::class)
    ->whereNumber('doctor')->where('fecha', '\d{4}-\d{2}-\d{2}')
    ->name('api.doctor.slots');

Route::post('/api/slot-holds', [SlotHoldController::class, 'store'])
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

// Rutas firmadas por email
Route::middleware('signed')->get('/email/cita/{cita}/{rol}/{accion}', EmailCitaActionController::class)
    ->where('rol', '^(paciente|doctor)$')->where('accion', '^(aceptar|cancelar)$')
    ->name('email.cita.action');

// =========================== DEMO (Pública) ===========================
Route::prefix('demo')->name('demo.')->group(function () {
    Route::get('/', [DemoDashboardController::class, 'index'])->name('index');
    
    // Superadmin
    Route::prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'superadminDashboard'])->name('dashboard');
        Route::get('/administradores', [DemoDashboardController::class, 'superadminAdministradores'])->name('administradores');
        Route::get('/usuarios', [DemoDashboardController::class, 'superadminUsuarios'])->name('usuarios');
        Route::get('/solicitudes', [DemoDashboardController::class, 'superadminSolicitudes'])->name('solicitudes');
        Route::get('/personalizacion', [DemoDashboardController::class, 'superadminPersonalizacion'])->name('personalizacion');
        Route::get('/mantenimiento', [DemoDashboardController::class, 'superadminMantenimiento'])->name('mantenimiento');
    });

    // Admin
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'adminDashboard'])->name('dashboard');
        Route::get('/usuarios', [DemoDashboardController::class, 'adminUsuarios'])->name('usuarios');
        Route::get('/registrar-usuario', [DemoDashboardController::class, 'adminRegistrarUsuario'])->name('registrar-usuario');
        Route::get('/personalizacion', [DemoDashboardController::class, 'adminPersonalizacion'])->name('personalizacion');
        Route::get('/historial-clinico', [DemoDashboardController::class, 'adminHistorialClinico'])->name('historial-clinico');
        Route::get('/horarios', [DemoDashboardController::class, 'adminHorarios'])->name('horarios');
        Route::get('/recordatorios', [DemoDashboardController::class, 'adminRecordatorios'])->name('recordatorios');
        Route::get('/agendar-manualmente', [DemoDashboardController::class, 'adminAgendarManualmente'])->name('agendar-manualmente');
        Route::get('/cambios-citas', [DemoDashboardController::class, 'adminCambiosCitas'])->name('cambios-citas');
        Route::get('/gestion-pagos', [DemoDashboardController::class, 'adminGestionPagos'])->name('gestion-pagos');
        Route::get('/perfil', [DemoDashboardController::class, 'adminPerfil'])->name('perfil');
        Route::get('/notificaciones-contacto', [DemoDashboardController::class, 'adminNotificacionesContacto'])->name('notificaciones-contacto');
    });

    // Paciente
    Route::prefix('paciente')->name('paciente.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'pacienteDashboard'])->name('dashboard');
        Route::get('/citas', [DemoDashboardController::class, 'pacienteCitas'])->name('citas');
        Route::get('/pagos', [DemoDashboardController::class, 'pacientePagos'])->name('pagos');
        Route::get('/historial-clinico', [DemoDashboardController::class, 'pacienteHistorialClinico'])->name('historial-clinico');
        Route::get('/resultados', [DemoDashboardController::class, 'pacienteResultados'])->name('resultados');
        Route::get('/agendar-cita', [DemoDashboardController::class, 'pacienteAgendarCita'])->name('agendar-cita');
        Route::get('/perfil', [DemoDashboardController::class, 'pacientePerfil'])->name('perfil');
    });

    // Doctor
    Route::prefix('doctor')->name('doctor.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'doctorDashboard'])->name('dashboard');
        Route::get('/citas', [DemoDashboardController::class, 'doctorCitas'])->name('citas');
        Route::get('/pacientes', [DemoDashboardController::class, 'doctorPacientes'])->name('pacientes');
        Route::get('/historial-recetas', [DemoDashboardController::class, 'doctorHistorialRecetas'])->name('historial-recetas');
        Route::get('/agenda-semanal', [DemoDashboardController::class, 'doctorAgendaSemanal'])->name('agenda-semanal');
        Route::get('/mi-horario', [DemoDashboardController::class, 'doctorMiHorario'])->name('mi-horario');
        Route::get('/perfil', [DemoDashboardController::class, 'doctorPerfil'])->name('perfil');
    });

    // Laboratorio
    Route::prefix('laboratorio')->name('laboratorio.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'laboratorioDashboard'])->name('dashboard');
        Route::get('/citas-resultados', [DemoDashboardController::class, 'laboratorioCitasResultados'])->name('citas-resultados');
        Route::get('/horarios', [DemoDashboardController::class, 'laboratorioHorarios'])->name('horarios');
    });
});

Route::middleware('auth')
    ->get('/cita/comprobante/{token}', [CitaComprobanteController::class, 'showByToken'])
    ->name('citas.comprobante.show');

// =========================== ADMIN ===========================
Route::middleware(['auth', 'role:administrador'])
    ->prefix('admin')->name('admin.')->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/data', [AdminDashboardController::class, 'dashboardData'])->name('dashboard.data');
        Route::get('/dashboard/resumen', [AdminDashboardController::class, 'resumenGlobal'])->name('dashboard.resumen');
        Route::get('/dashboard/export-pdf', [AdminDashboardController::class, 'exportPdf'])->name('dashboard.export-pdf');

        // Perfil
        Route::get('/perfil', [AdminDashboardController::class, 'editarPerfil'])->name('perfil.edit');
        Route::post('/perfil', [AdminDashboardController::class, 'actualizarPerfil'])->name('perfil.update');

        // Personalizacion (requiere aprobacion)
        Route::get('/personalizacion', [AdminPersonalizacionController::class, 'index'])
            ->middleware('feature:personalizacion')
            ->name('personalizacion.index');
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
        Route::get('/personalizacion/contacto', [AdminPersonalizacionController::class, 'contactoEdit'])
            ->middleware('feature:personalizacion')
            ->name('personalizacion.contacto.edit');
        Route::put('/personalizacion/contacto', [AdminPersonalizacionController::class, 'contactoUpdate'])
            ->middleware('feature:personalizacion')
            ->name('personalizacion.contacto.update');
        Route::post('/personalizacion/solicitar', [AdminPersonalizacionController::class, 'requestAccess'])
            ->name('personalizacion.request');

        // Usuarios
        Route::get('/usuarios', [AdminDashboardController::class, 'usuarios'])->name('usuarios.index');
        Route::get('/usuarios/check-email', [AdminDashboardController::class, 'checkEmail'])->name('usuarios.email.check');
        Route::get('/usuarios/crear', [AdminDashboardController::class, 'usuariosCreate'])->name('usuarios.create');
        Route::post('/usuarios', [AdminDashboardController::class, 'usuariosStore'])->name('usuarios.store');
        Route::get('/usuarios/{user}', [AdminDashboardController::class, 'usuariosShow'])->name('usuarios.show');
        Route::get('/usuarios/{user}/editar', [AdminDashboardController::class, 'usuariosEdit'])->name('usuarios.edit');
        Route::put('/usuarios/{user}', [AdminDashboardController::class, 'usuariosUpdate'])->name('usuarios.update');
        Route::delete('/usuarios/{user}', [AdminDashboardController::class, 'usuariosDestroy'])->name('usuarios.destroy');

        // Exportes usuarios
        Route::get('/usuarios/export/excel', [AdminDashboardController::class, 'usuariosExportExcel'])->name('usuarios.export.excel');
        Route::get('/usuarios/export/pdf', [AdminDashboardController::class, 'usuariosExportPdf'])->name('usuarios.export.pdf');

        // Estado de cuenta
        Route::patch('/usuarios/{user}/block', [AdminUserStatusController::class, 'block'])->name('usuarios.block');
        Route::patch('/usuarios/{user}/suspend', [AdminUserStatusController::class, 'suspend'])->name('usuarios.suspend');
        Route::patch('/usuarios/{user}/activate', [AdminUserStatusController::class, 'activate'])->name('usuarios.activate');
        Route::patch('/usuarios/{user}/deactivate', [AdminUserStatusController::class, 'deactivate'])->name('usuarios.deactivate');

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
        Route::get('/cambios-citas', [\App\Http\Controllers\Admin\CitaEventosController::class, 'index'])->name('cambios-citas.index');
        Route::get('/cambios-citas/export/excel', [\App\Http\Controllers\Admin\CitaEventosController::class, 'exportExcel'])->name('cambios-citas.export.excel');
        Route::get('/cambios-citas/export/pdf', [\App\Http\Controllers\Admin\CitaEventosController::class, 'exportPdf'])->name('cambios-citas.export.pdf');
        Route::get('/recordatorios', [AdminRecordatorioController::class, 'index'])->name('recordatorios.index');
        Route::get('/recordatorios/enviados', [AdminRecordatorioController::class, 'enviados'])->name('recordatorios.enviados');
        Route::patch('/recordatorios/{recordatorio}/enviado', [AdminRecordatorioController::class, 'marcarEnviado'])->name('recordatorios.enviado');
        Route::patch('/recordatorios/{recordatorio}/omitido', [AdminRecordatorioController::class, 'marcarOmitido'])->name('recordatorios.omitido');

        // Historial clínico (solo lectura)
        Route::get('/historial', [AdminHistorialController::class, 'index'])->name('historial.index');
        Route::get('/historial/paciente/{paciente}', [AdminHistorialController::class, 'paciente'])->name('historial.paciente');
        Route::get('/historial/nota/{nota}', [AdminHistorialController::class, 'showNote'])->name('historial.nota');
        Route::get('/historial/{nota}', [AdminHistorialController::class, 'show'])->name('historial.show');
        Route::get('/historial/paciente/{paciente}/exportar-pdf', [AdminHistorialController::class, 'exportPdf'])->name('historial.pdf');

        // Gestión de pagos
        Route::get('/pagos', [AdminPagoController::class, 'index'])->name('pagos.index');
        Route::get('/pagos/{pago}', [AdminPagoController::class, 'show'])->name('pagos.show');
        Route::post('/pagos/{pago}/monto', [AdminPagoController::class, 'actualizarMonto'])->name('pagos.monto.update');
        Route::post('/pagos/{pago}/metodo', [AdminPagoController::class, 'actualizarMetodo'])->name('pagos.metodo.update');
        Route::post('/pagos/{pago}/aprobar', [AdminPagoController::class, 'aprobar'])->name('pagos.aprobar');
        Route::post('/pagos/{pago}/rechazar', [AdminPagoController::class, 'rechazar'])->name('pagos.rechazar');
        Route::post('/pagos/{pago}/anular', [AdminPagoController::class, 'anular'])->name('pagos.anular');
        Route::get('/pagos/{pago}/comprobante', [AdminPagoController::class, 'comprobante'])->name('pagos.comprobante');
        Route::get('/pagos/{pago}/orden.pdf', [AdminPagoController::class, 'ordenPdf'])->name('pagos.orden.pdf');
        Route::get('/pagos/{pago}/recibo.pdf', [AdminPagoController::class, 'reciboPdf'])->name('pagos.recibo.pdf');

        // Excepción de agendamiento por pagos pendientes (override)
        Route::get('/citas/override/crear', [AdminCitaOverrideController::class, 'create'])->name('citas.override.create');
        Route::post('/citas/override', [AdminCitaOverrideController::class, 'store'])->name('citas.override.store');
        Route::get('/citas/{cita}/prioridad', [CitaPrioridadController::class, 'editAdmin'])->name('citas.prioridad.edit');
        Route::patch('/citas/{cita}/prioridad', [CitaPrioridadController::class, 'updateAdmin'])->name('citas.prioridad.update');
        // Notificaciones de contacto
        Route::get('/contacto/mensajes', [AdminContactMessageController::class, 'index'])->name('contacto.mensajes');
        Route::get('/contacto/mensajes/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contacto.mensajes.show');
    });

// ======================== SUPERADMIN ========================
Route::middleware(['auth', 'role:superadmin'])
    ->prefix('superadmin')->name('superadmin.')->group(function () {

        Route::get('/dashboard', [SuperadminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/data', [SuperadminDashboardController::class, 'dashboardData'])->name('dashboard.data');
        Route::get('/dashboard/export-pdf', [SuperadminDashboardController::class, 'exportPdf'])->name('dashboard.export-pdf');
        Route::get('/usuarios', [SuperadminDashboardController::class, 'users'])->name('users.index');

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
        Route::get('/personalizacion', [SuperadminPersonalizacionController::class, 'index'])->name('personalizacion.index');
        Route::get('/personalizacion/bienvenida', [SuperadminPersonalizacionController::class, 'edit'])
            ->name('personalizacion.bienvenida.edit');
        Route::put('/personalizacion/bienvenida', [SuperadminPersonalizacionController::class, 'update'])
            ->name('personalizacion.bienvenida.update');
        Route::get('/personalizacion/servicios', [SuperadminPersonalizacionController::class, 'serviciosEdit'])
            ->name('personalizacion.servicios.edit');
        Route::put('/personalizacion/servicios', [SuperadminPersonalizacionController::class, 'serviciosUpdate'])
            ->name('personalizacion.servicios.update');
        Route::get('/personalizacion/contacto', [SuperadminPersonalizacionController::class, 'contactoEdit'])
            ->name('personalizacion.contacto.edit');
        Route::put('/personalizacion/contacto', [SuperadminPersonalizacionController::class, 'contactoUpdate'])
            ->name('personalizacion.contacto.update');

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
    Route::get('/crear-cita', [CitaController::class, 'create'])->middleware('no_pending_payments')->name('paciente.crear-cita');
    Route::post('/crear-cita', [CitaController::class, 'store'])->middleware('no_pending_payments')->name('paciente.crear-cita.store');
    Route::post('/citas/{id}/cancelar', [CitaController::class, 'cancelar'])->name('paciente.citas.cancelar');
    Route::get('/citas/{cita}/comprobante.pdf', [CitaComprobanteController::class, 'pdfPaciente'])->name('paciente.citas.comprobante.pdf');
    Route::get('/editar-cita/{id}', [CitaController::class, 'edit'])->name('paciente.editar-cita');
    Route::put('/editar-cita/{id}', [CitaController::class, 'actualizar'])->name('paciente.editar-cita.update');
    Route::get('/pagos', [PacientePagoController::class, 'index'])->name('paciente.pagos.index');
    Route::post('/pagos/{pago}/enviar', [PacientePagoController::class, 'submit'])->name('paciente.pagos.submit');
    Route::get('/pagos/{pago}/comprobante', [PacientePagoController::class, 'comprobante'])->name('paciente.pagos.comprobante');
    Route::get('/pagos/{pago}/orden.pdf', [PacientePagoController::class, 'ordenPdf'])->name('paciente.pagos.orden.pdf');
    Route::get('/pagos/{pago}/recibo.pdf', [PacientePagoController::class, 'reciboPdf'])->name('paciente.pagos.recibo.pdf');
    Route::get('/laboratorio', [PacienteLaboratorioController::class, 'index'])->name('paciente.laboratorio.index');
    Route::get('/laboratorio/{orden}/descargar', [PacienteLaboratorioController::class, 'download'])
        ->whereNumber('orden')->name('paciente.laboratorio.download');
    Route::get('/laboratorio/solicitudes/{order}/descargar', [PacienteLaboratorioController::class, 'downloadAutoOrder'])
        ->whereNumber('order')->name('paciente.lab-orders.download');
    Route::get('/historial', [PacienteHistorialController::class, 'index'])->name('paciente.historial');
    Route::get('/historial/{nota}', [PacienteHistorialController::class, 'show'])->name('paciente.historial.show');
    Route::get('/certificados/{certificado}', [PacienteCertificadoMedicoController::class, 'show'])
        ->name('paciente.certificados.show');
    Route::get('/certificados/{certificado}/descargar', [PacienteCertificadoMedicoController::class, 'download'])
        ->name('paciente.certificados.download');
    Route::view('/mensajes', 'paciente.mensajes')->name('paciente.mensajes');

    // Dependientes routes
    Route::get('/dependientes', [DependienteController::class, 'index'])->name('paciente.dependientes.index');
    Route::get('/dependientes/crear', [DependienteController::class, 'create'])->name('paciente.dependientes.create');
    Route::post('/dependientes', [DependienteController::class, 'store'])->name('paciente.dependientes.store');
    Route::get('/dependientes/{dependiente}/editar', [DependienteController::class, 'edit'])->name('paciente.dependientes.edit');
    Route::put('/dependientes/{dependiente}', [DependienteController::class, 'update'])->name('paciente.dependientes.update');
    Route::patch('/dependientes/{dependiente}/activar', [DependienteController::class, 'activate'])->name('paciente.dependientes.activate');
    Route::patch('/dependientes/{dependiente}/desactivar', [DependienteController::class, 'deactivate'])->name('paciente.dependientes.deactivate');
    Route::delete('/dependientes/{dependiente}', [DependienteController::class, 'destroy'])->name('paciente.dependientes.destroy');
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
    Route::get('/citas/{cita}/prioridad', [CitaPrioridadController::class, 'editDoctor'])->name('doctor.citas.prioridad.edit');
    Route::patch('/citas/{cita}/prioridad', [CitaPrioridadController::class, 'updateDoctor'])->name('doctor.citas.prioridad.update');
    Route::get('/agenda', [DoctorDashboardController::class, 'agenda'])->name('doctor.agenda');

    Route::get('/disponibilidad/check', [CitaController::class, 'checkDisponibilidad'])
        ->name('doctor.disponibilidad.check');

    Route::post('/citas/{cita}/proxima/planificar', [CitaController::class, 'proximaPlanificada'])
        ->whereNumber('cita')->name('doctor.citas.proxima.planificada');
    Route::post('/citas/{cita}/proxima/{control}/cancelar', [CitaController::class, 'cancelarControlPlanificado'])
        ->whereNumber(['cita', 'control'])->name('doctor.citas.proxima.cancelar');

    // Recetas
    Route::get('/recetas', [RecetaController::class, 'index'])->name('doctor.recetas.index');
    Route::get('/recetas/crear/{cita}', [RecetaController::class, 'create'])->name('doctor.recetas.create');
    Route::post('/recetas', [RecetaController::class, 'store'])->name('doctor.recetas.store');
    Route::get('/recetas/editar/{cita}', [RecetaController::class, 'edit'])->name('doctor.recetas.edit');
    Route::post('/recetas/actualizar', [RecetaController::class, 'update'])->name('doctor.recetas.update');
    Route::post('/recetas/reenviar/{cita}', [RecetaController::class, 'resend'])->name('doctor.recetas.resend');
    Route::get('/recetas/descargar/{cita}', [RecetaController::class, 'download'])->name('doctor.recetas.download');

    // Certificados medicos
    Route::get('/citas/{cita}/certificado-medico/crear', [DoctorCertificadoMedicoController::class, 'create'])
        ->name('doctor.certificados.create');
    Route::post('/citas/{cita}/certificado-medico', [DoctorCertificadoMedicoController::class, 'store'])
        ->name('doctor.certificados.store');
    Route::get('/certificados/{certificado}', [DoctorCertificadoMedicoController::class, 'show'])
        ->name('doctor.certificados.show');
    Route::get('/certificados/{certificado}/descargar', [DoctorCertificadoMedicoController::class, 'download'])
        ->name('doctor.certificados.download');
    Route::post('/certificados/{certificado}/reenviar', [DoctorCertificadoMedicoController::class, 'resend'])
        ->name('doctor.certificados.resend');

    Route::get('/pedidos-laboratorio', [PedidoLaboratorioController::class, 'index'])
        ->name('doctor.pedidos-laboratorio.index');
    Route::get('/citas/{cita}/pedido-laboratorio/crear', [PedidoLaboratorioController::class, 'create'])
        ->whereNumber('cita')
        ->name('doctor.pedidos-laboratorio.create');
    Route::post('/citas/{cita}/pedido-laboratorio', [PedidoLaboratorioController::class, 'store'])
        ->whereNumber('cita')
        ->name('doctor.pedidos-laboratorio.store');
    Route::get('/citas/{cita}/pedido-laboratorio/editar', [PedidoLaboratorioController::class, 'edit'])
        ->whereNumber('cita')
        ->name('doctor.pedidos-laboratorio.edit');
    Route::post('/citas/{cita}/pedido-laboratorio/editar', [PedidoLaboratorioController::class, 'update'])
        ->whereNumber('cita')
        ->name('doctor.pedidos-laboratorio.update');

    Route::get('/pedidos-laboratorio/{pedido}/descargar', [PedidoLaboratorioController::class, 'download'])
        ->whereNumber('pedido')
        ->name('doctor.pedidos-laboratorio.download');
    Route::post('/pedidos-laboratorio/{pedido}/reenviar', [PedidoLaboratorioController::class, 'resend'])
        ->whereNumber('pedido')
        ->name('doctor.pedidos-laboratorio.resend');

    // Nota clínica SOAP
    Route::get('/citas/{cita}/historial-clinico', [DoctorSoapController::class, 'show'])->name('doctor.citas.soap');
    Route::post('/citas/{cita}/historial-clinico', [DoctorSoapController::class, 'store'])->name('doctor.citas.soap.store');
    Route::post('/citas/{cita}/historial-clinico/firmar', [DoctorSoapController::class, 'firmar'])->name('doctor.citas.soap.firmar');
    Route::post('/citas/{cita}/historial-clinico/enmienda', [DoctorSoapController::class, 'enmienda'])->name('doctor.citas.soap.enmienda');
    Route::get('/pacientes', [DoctorHistorialController::class, 'index'])->name('doctor.pacientes.index');
    Route::get('/pacientes/{paciente}/historial', [DoctorHistorialController::class, 'show'])->name('doctor.pacientes.historial');
    Route::put('/pacientes/{paciente}/historial', [DoctorHistorialController::class, 'update'])->name('doctor.pacientes.historial.update');
    Route::get('/pacientes/{paciente}/historial/exportar-pdf', [DoctorHistorialController::class, 'exportPdf'])->name('doctor.pacientes.historial.pdf');

    // Horarios
    Route::get('/horario', [DoctorHorarioController::class, 'index'])->name('doctor.horario.index');
    Route::post('/horario', [DoctorHorarioController::class, 'store'])->name('doctor.horario.store');
    Route::get('/horario/{horario}/edit', [DoctorHorarioController::class, 'edit'])->name('doctor.horario.edit');
    Route::put('/horario/{horario}', [DoctorHorarioController::class, 'update'])->name('doctor.horario.update');
    Route::delete('/horario/{horario}', [DoctorHorarioController::class, 'destroy'])->name('doctor.horario.destroy');
    Route::post('/horario/generar', [DoctorHorarioController::class, 'generarRango'])->name('doctor.horario.generar');
});

// ======================== LABORATORIO =======================
Route::middleware(['auth', 'role:laboratorio'])->prefix('laboratorio')->name('laboratorio.')->group(function () {
    Route::get('/dashboard', [LaboratorioDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard/data', [LaboratorioDashboardController::class, 'dashboardData'])->name('dashboard.data');

    Route::get('/horario', [LaboratorioHorarioController::class, 'index'])->name('horario.index');
    Route::post('/horario', [LaboratorioHorarioController::class, 'store'])->name('horario.store');
    Route::get('/horario/{horario}/edit', [LaboratorioHorarioController::class, 'edit'])->name('horario.edit');
    Route::put('/horario/{horario}', [LaboratorioHorarioController::class, 'update'])->name('horario.update');
    Route::delete('/horario/{horario}', [LaboratorioHorarioController::class, 'destroy'])->name('horario.destroy');
    Route::post('/horario/generar', [LaboratorioHorarioController::class, 'generarRango'])->name('horario.generar');

    Route::get('/ordenes', [LaboratorioOrdenController::class, 'index'])->name('ordenes.index');
    Route::post('/ordenes/{orden}/muestra', [LaboratorioOrdenController::class, 'marcarMuestra'])
        ->whereNumber('orden')->name('ordenes.muestra');
    Route::post('/ordenes/{orden}/resultado', [LaboratorioOrdenController::class, 'subirResultado'])
        ->whereNumber('orden')->name('ordenes.resultado');
    Route::get('/ordenes/{orden}/download', [LaboratorioOrdenController::class, 'download'])
        ->whereNumber('orden')->name('ordenes.download');
    Route::post('/ordenes/solicitudes/{labOrder}/muestra', [LaboratorioOrdenController::class, 'marcarMuestraAutoOrder'])
        ->whereNumber('labOrder')->name('lab-orders.muestra');
    Route::post('/ordenes/solicitudes/{labOrder}/resultado', [LaboratorioOrdenController::class, 'subirResultadoAutoOrder'])
        ->whereNumber('labOrder')->name('lab-orders.resultado');
    Route::get('/ordenes/solicitudes/{labOrder}/download', [LaboratorioOrdenController::class, 'downloadAutoOrder'])
        ->whereNumber('labOrder')->name('lab-orders.download');

    // Pedidos de Laboratorio MVP
    Route::get('/pedidos-mvp', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'index'])->name('pedidos.index');
    Route::post('/pedidos-mvp/{pedido}/muestra', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'marcarMuestra'])->name('pedidos.muestra');
    Route::post('/pedidos-mvp/{pedido}/resultado', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'subirResultado'])->name('pedidos.resultado');
    Route::get('/pedidos-mvp/{pedido}/download-orden', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'downloadOrden'])->name('pedidos.download-orden');
    Route::get('/pedidos-mvp/{pedido}/download-resultado', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'downloadResultado'])->name('pedidos.download-resultado');

});

// =========================== LOGOUT ==========================
Route::post('/salir', [PublicPageController::class, 'logout'])->name('salir');
Route::get('/salir', [PublicPageController::class, 'logout'])->name('salir.get');

// =========================== CHATBOT ===========================
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
    Route::post('/chatbot/registrar-usuario', [ChatBotController::class, 'registrarUsuario'])->name('chatbot.registrarUsuario');
    Route::post('/chatbot/agendar', [ChatBotController::class, 'agendar'])->name('chatbot.agendar');
    Route::post('/chatbot/buscar-citas', [ChatBotController::class, 'buscarCitas'])->name('chatbot.buscarCitas');
    Route::post('/chatbot/cancelar', [ChatBotController::class, 'cancelar'])->name('chatbot.cancelar');
    Route::post('/chatbot/reagendar', [ChatBotController::class, 'reagendar'])->name('chatbot.reagendar');
    Route::post('/chatbot/perfil', [ChatBotController::class, 'perfil'])->name('chatbot.perfil');
    Route::post('/chatbot/perfil/actualizar', [ChatBotController::class, 'actualizarPerfil'])->name('chatbot.perfil.actualizar');
});

Route::middleware('auth')->group(function () {

    Route::patch('/panel/theme', [PanelThemeController::class, 'update'])->name('panel.theme.update');
});



Route::middleware('auth')->group(function () {
    Route::get('/cobro/{token}', [PagoLookupController::class, 'showByToken'])->name('pagos.token.show');
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

// Asigna nombre a rutas del framework/UI que se registran sin ->name()
foreach (Route::getRoutes() as $route) {
    if ($route->getName() !== null) {
        continue;
    }

    $methods = $route->methods();
    $uri = $route->uri();

    if (in_array('POST', $methods, true) && $uri === 'login') {
        $route->name('auth.login');
        continue;
    }

    if (in_array('POST', $methods, true) && $uri === 'password/confirm') {
        $route->name('auth.password.confirm');
        continue;
    }

    if (in_array('GET', $methods, true) && $uri === 'up') {
        $route->name('system.health');
    }
}

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
use App\Http\Controllers\PagoLookupController;
use App\Http\Controllers\PaymentReceiptVerificationController;
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
use App\Http\Controllers\Laboratorio\PedidoLaboratorioResultadoController as LaboratorioPedidoResultadoController;
use App\Http\Controllers\Paciente\AdminController as PacienteDashboardController;
use App\Http\Controllers\Paciente\CertificadoMedicoController as PacienteCertificadoMedicoController;
use App\Http\Controllers\Paciente\HistorialController as PacienteHistorialController;
use App\Http\Controllers\Paciente\LaboratorioController as PacienteLaboratorioController;
use App\Http\Controllers\Paciente\PagoController as PacientePagoController;
use App\Http\Controllers\DependienteController;
use App\Http\Controllers\PanelThemeController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\AvatarMediaController;
use App\Http\Controllers\DependentAvatarMediaController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Superadmin\AdminsController as SuperadminAdminsController;
use App\Http\Controllers\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\Superadmin\MaintenanceController as SuperadminMaintenanceController;
use App\Http\Controllers\Superadmin\PersonalizacionController as SuperadminPersonalizacionController;
use App\Http\Controllers\Superadmin\PersonalizacionRequestController as SuperadminPersonalizacionRequestController;
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

// =========================== DEMO (Pública) ===========================
// El middleware 'demo.isolation' bloquea cualquier método HTTP que no sea GET/HEAD
// y limpia cualquier clave de sesión persistida por simulaciones anteriores.
// Esto garantiza que NINGUNA acción demo sobreviva a una recarga o regreso.
Route::prefix('demo')->name('demo.')->middleware('demo.isolation')->group(function () {
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
        Route::get('/dashboard/data', [DemoDashboardController::class, 'adminDashboardData'])->name('dashboard.data');
        Route::get('/dashboard/resumen', [DemoDashboardController::class, 'adminDashboardResumen'])->name('dashboard.resumen');

        Route::get('/usuarios', [DemoDashboardController::class, 'adminUsuarios'])->name('usuarios.index');
        Route::get('/usuarios/crear', [DemoDashboardController::class, 'adminRegistrarUsuario'])->name('usuarios.create');
        Route::post('/usuarios', [DemoDashboardController::class, 'adminUsuariosStore'])->name('usuarios.store');
        Route::get('/usuarios/{id}', [DemoDashboardController::class, 'adminUsuariosShow'])->name('usuarios.show');
        Route::get('/usuarios/{id}/editar', [DemoDashboardController::class, 'adminUsuariosEdit'])->name('usuarios.edit');
        Route::put('/usuarios/{id}', [DemoDashboardController::class, 'adminUsuariosUpdate'])->name('usuarios.update');
        Route::delete('/usuarios/{id}', [DemoDashboardController::class, 'adminUsuariosDestroy'])->name('usuarios.destroy');

        Route::patch('/usuarios/{id}/block', [DemoDashboardController::class, 'adminUsuariosBlock'])->name('usuarios.block');
        Route::patch('/usuarios/{id}/suspend', [DemoDashboardController::class, 'adminUsuariosSuspend'])->name('usuarios.suspend');
        Route::patch('/usuarios/{id}/activate', [DemoDashboardController::class, 'adminUsuariosActivate'])->name('usuarios.activate');
        Route::patch('/usuarios/{id}/deactivate', [DemoDashboardController::class, 'adminUsuariosDeactivate'])->name('usuarios.deactivate');

        Route::get('/personalizacion', [DemoDashboardController::class, 'adminPersonalizacion'])->name('personalizacion.index');
        Route::get('/personalizacion/bienvenida', [DemoDashboardController::class, 'adminPersonalizacionBienvenidaEdit'])->name('personalizacion.bienvenida.edit');
        Route::put('/personalizacion/bienvenida', [DemoDashboardController::class, 'adminPersonalizacionBienvenidaUpdate'])->name('personalizacion.bienvenida.update');
        Route::get('/personalizacion/servicios', [DemoDashboardController::class, 'adminPersonalizacionServiciosEdit'])->name('personalizacion.servicios.edit');
        Route::put('/personalizacion/servicios', [DemoDashboardController::class, 'adminPersonalizacionServiciosUpdate'])->name('personalizacion.servicios.update');
        Route::get('/personalizacion/contacto', [DemoDashboardController::class, 'adminPersonalizacionContactoEdit'])->name('personalizacion.contacto.edit');
        Route::put('/personalizacion/contacto', [DemoDashboardController::class, 'adminPersonalizacionContactoUpdate'])->name('personalizacion.contacto.update');
        Route::post('/personalizacion/solicitar', [DemoDashboardController::class, 'adminPersonalizacionRequestAccess'])->name('personalizacion.request');

        Route::get('/historial-clinico', [DemoDashboardController::class, 'adminHistorialClinico'])->name('historial.index');
        Route::get('/historial/paciente/{id}', [DemoDashboardController::class, 'adminHistorialPaciente'])->name('historial.paciente');
        Route::get('/historial/{id}', [DemoDashboardController::class, 'adminHistorialShow'])->name('historial.show');

        Route::get('/horarios', [DemoDashboardController::class, 'adminHorarios'])->name('horarios');
        Route::get('/horarios/crear', [DemoDashboardController::class, 'adminHorariosCreate'])->name('horarios.create');
        Route::post('/horarios', [DemoDashboardController::class, 'adminHorariosStore'])->name('horarios.store');
        Route::get('/horarios/{id}/editar', [DemoDashboardController::class, 'adminHorariosEdit'])->name('horarios.edit');
        Route::put('/horarios/{id}', [DemoDashboardController::class, 'adminHorariosUpdate'])->name('horarios.update');
        Route::delete('/horarios/{id}', [DemoDashboardController::class, 'adminHorariosDestroy'])->name('horarios.destroy');

        Route::get('/recordatorios', [DemoDashboardController::class, 'adminRecordatorios'])->name('recordatorios');
        Route::patch('/recordatorios/{id}/enviado', [DemoDashboardController::class, 'adminRecordatoriosEnviado'])->name('recordatorios.enviado');
        Route::patch('/recordatorios/{id}/omitido', [DemoDashboardController::class, 'adminRecordatoriosOmitido'])->name('recordatorios.omitido');
        // Alias para vistas que usan recordatorios.index
        Route::get('/recordatorios/index', [DemoDashboardController::class, 'adminRecordatorios'])->name('recordatorios.index');

        Route::get('/agendar-manualmente', [DemoDashboardController::class, 'adminAgendarManualmente'])->name('agendar-manualmente');
        Route::post('/citas/override', [DemoDashboardController::class, 'adminAgendarManualmenteStore'])->name('citas.override.store');
        // Alias para vistas que usan citas.override.create
        Route::get('/citas/override/create', [DemoDashboardController::class, 'adminAgendarManualmente'])->name('citas.override.create');
        Route::get('/citas/{id}/prioridad', [DemoDashboardController::class, 'adminCitasPrioridadEdit'])->name('citas.prioridad.edit');
        Route::patch('/citas/{id}/prioridad', [DemoDashboardController::class, 'adminCitasPrioridadUpdate'])->name('citas.prioridad.update');

        Route::get('/cambios-citas', [DemoDashboardController::class, 'adminCambiosCitas'])->name('cambios-citas');
        
        Route::get('/gestion-pagos', [DemoDashboardController::class, 'adminGestionPagos'])->name('gestion-pagos');
        // Alias para vistas que usan pagos.index
        Route::get('/gestion-pagos/index', [DemoDashboardController::class, 'adminGestionPagos'])->name('pagos.index');
        Route::get('/pagos/{id}', [DemoDashboardController::class, 'adminPagosShow'])->name('pagos.show');
        Route::post('/pagos/{id}/aprobar', [DemoDashboardController::class, 'adminPagosAprobar'])->name('pagos.aprobar');
        Route::post('/pagos/{id}/rechazar', [DemoDashboardController::class, 'adminPagosRechazar'])->name('pagos.rechazar');
        Route::post('/pagos/{id}/anular', [DemoDashboardController::class, 'adminPagosAnular'])->name('pagos.anular');
        // Alias para metodo y monto update (simulados)
        Route::patch('/pagos/{id}/metodo', [DemoDashboardController::class, 'adminPagosShow'])->name('pagos.metodo.update');
        Route::patch('/pagos/{id}/monto', [DemoDashboardController::class, 'adminPagosShow'])->name('pagos.monto.update');

        // Alias horarios.index
        Route::get('/horarios/index', [DemoDashboardController::class, 'adminHorarios'])->name('horarios.index');

        Route::get('/perfil', [DemoDashboardController::class, 'adminPerfil'])->name('perfil');
        Route::post('/perfil', [DemoDashboardController::class, 'adminPerfilUpdate'])->name('perfil.update');

        Route::get('/notificaciones-contacto', [DemoDashboardController::class, 'adminNotificacionesContacto'])->name('notificaciones-contacto');
        Route::get('/notificaciones-contacto/{id}', [DemoDashboardController::class, 'adminNotificacionesContactoShow'])->name('contacto.mensajes.show');
    });

    // Paciente
    Route::prefix('paciente')->name('paciente.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'pacienteDashboard'])->name('dashboard');
        Route::get('/citas', [DemoDashboardController::class, 'pacienteCitas'])->name('citas');
        Route::get('/crear-cita', [DemoDashboardController::class, 'pacienteAgendarCita'])->name('agendar-cita');
        Route::post('/crear-cita', [DemoDashboardController::class, 'pacienteAgendarCitaStore'])->name('crear-cita.store');
        Route::post('/citas/{id}/cancelar', [DemoDashboardController::class, 'pacienteCitasCancelar'])->name('citas.cancelar');
        Route::get('/editar-cita/{id}', [DemoDashboardController::class, 'pacienteCitasEdit'])->name('editar-cita');
        Route::put('/editar-cita/{id}', [DemoDashboardController::class, 'pacienteCitasUpdate'])->name('editar-cita.update');

        Route::get('/pagos', [DemoDashboardController::class, 'pacientePagos'])->name('pagos');
        Route::post('/pagos/{id}/enviar', [DemoDashboardController::class, 'pacientePagosSubmit'])->name('pagos.submit');

        Route::get('/historial-clinico', [DemoDashboardController::class, 'pacienteHistorialClinico'])->name('historial-clinico');
        Route::get('/historial/{id}', [DemoDashboardController::class, 'pacienteHistorialShow'])->name('historial.show');

        Route::get('/resultados', [DemoDashboardController::class, 'pacienteResultados'])->name('resultados');
        Route::get('/perfil', [DemoDashboardController::class, 'pacientePerfil'])->name('perfil');
        Route::post('/perfil', [DemoDashboardController::class, 'pacientePerfilUpdate'])->name('perfil.update');

        Route::get('/dependientes', [DemoDashboardController::class, 'pacienteDependientesIndex'])->name('dependientes.index');
        Route::get('/dependientes/crear', [DemoDashboardController::class, 'pacienteDependientesCreate'])->name('dependientes.create');
        Route::post('/dependientes', [DemoDashboardController::class, 'pacienteDependientesStore'])->name('dependientes.store');
        Route::get('/dependientes/{id}/editar', [DemoDashboardController::class, 'pacienteDependientesEdit'])->name('dependientes.edit');
        Route::put('/dependientes/{id}', [DemoDashboardController::class, 'pacienteDependientesUpdate'])->name('dependientes.update');
        Route::delete('/dependientes/{id}', [DemoDashboardController::class, 'pacienteDependientesDestroy'])->name('dependientes.destroy');

        // Aliases para nombres alternativos usados en vistas
        Route::get('/crear-cita/form', [DemoDashboardController::class, 'pacienteAgendarCita'])->name('crear-cita');
        Route::get('/historial-clinico/index', [DemoDashboardController::class, 'pacienteHistorialClinico'])->name('historial');
        Route::get('/pagos/index', [DemoDashboardController::class, 'pacientePagos'])->name('pagos.index');
        Route::get('/laboratorio/resultados', [DemoDashboardController::class, 'pacienteResultados'])->name('laboratorio.index');
    });

    // Doctor
    Route::prefix('doctor')->name('doctor.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'doctorDashboard'])->name('dashboard');
        Route::get('/dashboard/data', [DemoDashboardController::class, 'doctorDashboardData'])->name('dashboard.data');
        Route::get('/citas', [DemoDashboardController::class, 'doctorCitas'])->name('citas');
        Route::post('/citas/{id}/aceptar', [DemoDashboardController::class, 'doctorCitasAceptar'])->name('citas.aceptar');
        Route::post('/citas/{id}/rechazar', [DemoDashboardController::class, 'doctorCitasRechazar'])->name('citas.rechazar');
        Route::post('/citas/{id}/realizar', [DemoDashboardController::class, 'doctorCitasRealizar'])->name('citas.realizar');
        Route::get('/citas/{id}/prioridad', [DemoDashboardController::class, 'doctorCitasPrioridadEdit'])->name('citas.prioridad.edit');
        Route::patch('/citas/{id}/prioridad', [DemoDashboardController::class, 'doctorCitasPrioridadUpdate'])->name('citas.prioridad.update');

        Route::get('/recetas', [DemoDashboardController::class, 'doctorRecetasIndex'])->name('historial-recetas');
        Route::get('/recetas/crear/{cita}', [DemoDashboardController::class, 'doctorRecetasCreate'])->name('recetas.create');
        Route::post('/recetas', [DemoDashboardController::class, 'doctorRecetasStore'])->name('recetas.store');
        Route::get('/recetas/editar/{cita}', [DemoDashboardController::class, 'doctorRecetasEdit'])->name('recetas.edit');
        Route::post('/recetas/actualizar', [DemoDashboardController::class, 'doctorRecetasUpdate'])->name('recetas.update');

        Route::get('/citas/{cita}/certificado-medico/crear', [DemoDashboardController::class, 'doctorCertificadosCreate'])->name('certificados.create');
        Route::post('/citas/{cita}/certificado-medico', [DemoDashboardController::class, 'doctorCertificadosStore'])->name('certificados.store');

        Route::get('/pedidos-laboratorio', [DemoDashboardController::class, 'doctorPedidosLaboratorioIndex'])->name('pedidos-laboratorio.index');
        Route::get('/citas/{cita}/pedido-laboratorio/crear', [DemoDashboardController::class, 'doctorPedidosLaboratorioCreate'])->name('pedidos-laboratorio.create');
        Route::post('/citas/{cita}/pedido-laboratorio', [DemoDashboardController::class, 'doctorPedidosLaboratorioStore'])->name('pedidos-laboratorio.store');
        Route::get('/citas/{cita}/pedido-laboratorio/editar', [DemoDashboardController::class, 'doctorPedidosLaboratorioEdit'])->name('pedidos-laboratorio.edit');
        Route::post('/citas/{cita}/pedido-laboratorio/editar', [DemoDashboardController::class, 'doctorPedidosLaboratorioUpdate'])->name('pedidos-laboratorio.update');

        Route::get('/citas/{cita}/historial-clinico', [DemoDashboardController::class, 'doctorCitasSoap'])->name('citas.soap');
        Route::post('/citas/{cita}/historial-clinico', [DemoDashboardController::class, 'doctorCitasSoapStore'])->name('citas.soap.store');
        Route::post('/citas/{cita}/historial-clinico/firmar', [DemoDashboardController::class, 'doctorCitasSoapFirmar'])->name('citas.soap.firmar');
        Route::post('/citas/{cita}/historial-clinico/enmienda', [DemoDashboardController::class, 'doctorCitasSoapEnmienda'])->name('citas.soap.enmienda');

        Route::get('/pacientes', [DemoDashboardController::class, 'doctorPacientes'])->name('pacientes');
        // Alias pacientes.index
        Route::get('/pacientes/index', [DemoDashboardController::class, 'doctorPacientes'])->name('pacientes.index');
        Route::get('/pacientes/{id}/historial', [DemoDashboardController::class, 'doctorPacientesHistorial'])->name('pacientes.historial');
        Route::put('/pacientes/{id}/historial', [DemoDashboardController::class, 'doctorPacientesHistorialUpdate'])->name('pacientes.historial.update');

        Route::get('/agenda-semanal', [DemoDashboardController::class, 'doctorAgendaSemanal'])->name('agenda-semanal');
        Route::get('/mi-horario', [DemoDashboardController::class, 'doctorMiHorario'])->name('mi-horario');
        // Alias horario.index
        Route::get('/horario-index', [DemoDashboardController::class, 'doctorMiHorario'])->name('horario.index');
        Route::post('/horario', [DemoDashboardController::class, 'doctorHorarioStore'])->name('horario.store');
        Route::get('/horario/{id}/edit', [DemoDashboardController::class, 'doctorHorarioEdit'])->name('horario.edit');
        Route::put('/horario/{id}', [DemoDashboardController::class, 'doctorHorarioUpdate'])->name('horario.update');
        Route::delete('/horario/{id}', [DemoDashboardController::class, 'doctorHorarioDestroy'])->name('horario.destroy');

        Route::get('/perfil', [DemoDashboardController::class, 'doctorPerfil'])->name('perfil');
        Route::post('/perfil', [DemoDashboardController::class, 'doctorPerfilUpdate'])->name('perfil.update');
    });

    // Laboratorio
    Route::prefix('laboratorio')->name('laboratorio.')->group(function () {
        Route::get('/', [DemoDashboardController::class, 'laboratorioDashboard'])->name('dashboard');
        Route::get('/citas-resultados', [DemoDashboardController::class, 'laboratorioCitasResultados'])->name('citas-resultados');

        Route::get('/horarios', [DemoDashboardController::class, 'laboratorioHorarios'])->name('horarios');
        // Alias horario.index
        Route::get('/horario-index', [DemoDashboardController::class, 'laboratorioHorarios'])->name('horario.index');
        Route::post('/horario', [DemoDashboardController::class, 'laboratorioHorarioStore'])->name('horario.store');
        Route::get('/horario/{id}/edit', [DemoDashboardController::class, 'laboratorioHorarioEdit'])->name('horario.edit');
        Route::put('/horario/{id}', [DemoDashboardController::class, 'laboratorioHorarioUpdate'])->name('horario.update');
        Route::delete('/horario/{id}', [DemoDashboardController::class, 'laboratorioHorarioDestroy'])->name('horario.destroy');

        Route::get('/ordenes', [DemoDashboardController::class, 'laboratorioOrdenesIndex'])->name('ordenes.index');
        Route::post('/ordenes/{orden}/muestra', [DemoDashboardController::class, 'laboratorioOrdenesMuestra'])->name('ordenes.muestra');
        Route::post('/ordenes/{orden}/resultado', [DemoDashboardController::class, 'laboratorioOrdenesResultado'])->name('ordenes.resultado');

        Route::get('/pedidos-mvp', [DemoDashboardController::class, 'laboratorioPedidosMvpIndex'])->name('pedidos.index');
        Route::post('/pedidos-mvp/{id}/muestra', [DemoDashboardController::class, 'laboratorioPedidosMuestra'])->name('pedidos.muestra');
        Route::post('/pedidos-mvp/{id}/resultado', [DemoDashboardController::class, 'laboratorioPedidosResultado'])->name('pedidos.resultado');
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
        Route::get('/personalizacion/servicios/batch/{uuid}', [AdminPersonalizacionController::class, 'serviciosBatchStatus'])
            ->middleware('feature:personalizacion')
            ->name('personalizacion.servicios.batch');
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
        Route::get('/personalizacion/servicios/batch/{uuid}', [SuperadminPersonalizacionController::class, 'serviciosBatchStatus'])
            ->name('personalizacion.servicios.batch');
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
    Route::get('/laboratorio/pedidos/{pedido}/descargar', [PacienteLaboratorioController::class, 'downloadPedido'])
        ->whereNumber('pedido')->name('paciente.laboratorio.pedido.download');
    Route::get('/laboratorio/pedidos/{pedido}/orden/ver', [PacienteLaboratorioController::class, 'downloadOrdenPedidoInline'])
        ->whereNumber('pedido')->name('paciente.laboratorio.pedido.orden.ver');
    Route::get('/laboratorio/pedidos/{pedido}/orden/descargar', [PacienteLaboratorioController::class, 'downloadOrdenPedidoAttachment'])
        ->whereNumber('pedido')->name('paciente.laboratorio.pedido.orden.descargar');
    Route::get('/laboratorio/solicitudes/{order}/descargar', [PacienteLaboratorioController::class, 'downloadAutoOrder'])
        ->whereNumber('order')->name('paciente.lab-orders.download');
    Route::get('/historial', [PacienteHistorialController::class, 'index'])->name('paciente.historial');
    Route::get('/historial/{nota}', [PacienteHistorialController::class, 'show'])->name('paciente.historial.show');
    Route::get('/certificados/{certificado}', [PacienteCertificadoMedicoController::class, 'show'])
        ->name('paciente.certificados.show');
    Route::get('/certificados/{certificado}/descargar', [PacienteCertificadoMedicoController::class, 'download'])
        ->name('paciente.certificados.download');
    Route::get('/recetas/{cita}/descargar', [RecetaController::class, 'download'])
        ->name('paciente.recetas.download');
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
    Route::get('/pedidos-laboratorio/{pedido}/resultado', [PedidoLaboratorioController::class, 'downloadResultado'])
        ->whereNumber('pedido')
        ->name('doctor.pedidos-laboratorio.resultado.download');
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
    Route::get('/pedidos-mvp/{pedido}/descargar-orden', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'downloadOrdenAttachment'])->name('pedidos.descargar-orden');
    Route::get('/pedidos-mvp/{pedido}/download-resultado', [\App\Http\Controllers\Laboratorio\PedidoLaboratorioController::class, 'downloadResultado'])->name('pedidos.download-resultado');

    Route::get('/pedidos/{pedido}/resultados', [LaboratorioPedidoResultadoController::class, 'create'])
        ->whereNumber('pedido')->name('pedidos.resultados.form');
    Route::post('/pedidos/{pedido}/resultados/borrador', [LaboratorioPedidoResultadoController::class, 'draft'])
        ->whereNumber('pedido')->name('pedidos.resultados.draft');
    Route::post('/pedidos/{pedido}/resultados/preview', [LaboratorioPedidoResultadoController::class, 'preview'])
        ->whereNumber('pedido')->name('pedidos.resultados.preview');
    Route::post('/pedidos/{pedido}/resultados/publicar', [LaboratorioPedidoResultadoController::class, 'publish'])
        ->whereNumber('pedido')->name('pedidos.resultados.publish');
    Route::post('/pedidos/{pedido}/resultados/reenviar', [LaboratorioPedidoResultadoController::class, 'resend'])
        ->whereNumber('pedido')->name('pedidos.resultados.resend');
    Route::get('/pedidos/{pedido}/resultados/descargar', [LaboratorioPedidoResultadoController::class, 'download'])
        ->whereNumber('pedido')->name('pedidos.resultados.download');

    // Administración institucional del catálogo de laboratorio (66 exámenes)
    Route::get('/catalogo', [\App\Http\Controllers\Laboratorio\CatalogoLaboratorioController::class, 'index'])->name('catalogo.index');
    Route::get('/catalogo/{exam}/editar', [\App\Http\Controllers\Laboratorio\CatalogoLaboratorioController::class, 'edit'])->name('catalogo.edit');
    Route::put('/catalogo/{exam}', [\App\Http\Controllers\Laboratorio\CatalogoLaboratorioController::class, 'update'])->name('catalogo.update');

});

// =========================== LOGOUT ==========================
Route::post('/salir', [PublicPageController::class, 'logout'])->name('salir');
Route::get('/salir', [PublicPageController::class, 'logout'])->name('salir.get');

// =========================== CHATBOT ===========================
// 1. CAPTCHA public endpoints (with strict throttles)
Route::middleware('throttle:captcha.challenge')->group(function () {
    Route::get('/captcha/challenge', [\App\Http\Controllers\Captcha\CaptchaController::class, 'challenge'])
        ->name('captcha.challenge');
});
Route::middleware('throttle:captcha.verify')->group(function () {
    Route::post('/captcha/verify', [\App\Http\Controllers\Captcha\CaptchaController::class, 'verify'])
        ->name('captcha.verify');
});
Route::get('/captcha/challenge/{token}/image/{position}', [\App\Http\Controllers\Captcha\CaptchaController::class, 'image'])
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

// Agendar: supports both guest (captcha verified) and OTP-identified session flows.
// Deliberately kept outside captcha_verified group so the OTP session can access it directly.
Route::post('/chatbot/agendar', [ChatBotController::class, 'agendar'])
    ->middleware('throttle:chatbot.message')
    ->name('chatbot.agendar');

// Session cleanup (widget reset)
Route::post('/chatbot/finalizar', [ChatBotController::class, 'finalizar'])
    ->name('chatbot.finalizar');


// 3. Chatbot Transactional/Patient endpoints (protected by Identity OTP, throttle message)
Route::middleware(['chatbot_identity', 'throttle:chatbot.message'])->group(function () {
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

Route::middleware('auth')->group(function () {
    Route::patch('/panel/theme', [PanelThemeController::class, 'update'])->name('panel.theme.update');
    Route::get('/auth/must-change-password', [\App\Http\Controllers\Auth\MustChangePasswordController::class, 'show'])->name('auth.must-change-password');
    Route::post('/auth/must-change-password', [\App\Http\Controllers\Auth\MustChangePasswordController::class, 'update'])->name('auth.must-change-password.update');
});

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

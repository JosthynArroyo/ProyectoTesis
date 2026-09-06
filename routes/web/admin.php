<?php

use App\Http\Controllers\Admin\AdminController as AdminDashboardController;
use App\Http\Controllers\Admin\CitaEventosController;
use App\Http\Controllers\Admin\CitaOverrideController as AdminCitaOverrideController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\HistorialController as AdminHistorialController;
use App\Http\Controllers\Admin\HorarioController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\PersonalizacionController as AdminPersonalizacionController;
use App\Http\Controllers\Admin\RecordatorioController as AdminRecordatorioController;
use App\Http\Controllers\Admin\UserStatusController as AdminUserStatusController;
use App\Http\Controllers\CitaPrioridadController;
use App\Http\Controllers\ExportCitasController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/personalizacion/bienvenida/batch/{uuid}', [AdminPersonalizacionController::class, 'welcomeBatchStatus'])
            ->middleware('feature:personalizacion')
            ->name('personalizacion.bienvenida.batch');
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
        Route::get('/cambios-citas', [CitaEventosController::class, 'index'])->name('cambios-citas.index');
        Route::get('/cambios-citas/export/excel', [CitaEventosController::class, 'exportExcel'])->name('cambios-citas.export.excel');
        Route::get('/cambios-citas/export/pdf', [CitaEventosController::class, 'exportPdf'])->name('cambios-citas.export.pdf');
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

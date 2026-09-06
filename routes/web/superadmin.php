<?php

use App\Http\Controllers\Superadmin\AdminsController as SuperadminAdminsController;
use App\Http\Controllers\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\Superadmin\DatabaseBackupController as SuperadminDatabaseBackupController;
use App\Http\Controllers\Superadmin\MaintenanceController as SuperadminMaintenanceController;
use App\Http\Controllers\Superadmin\PersonalizacionController as SuperadminPersonalizacionController;
use App\Http\Controllers\Superadmin\PersonalizacionRequestController as SuperadminPersonalizacionRequestController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/personalizacion/bienvenida/batch/{uuid}', [SuperadminPersonalizacionController::class, 'welcomeBatchStatus'])
            ->name('personalizacion.bienvenida.batch');
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

        // Respaldos de base de datos
        Route::get('/respaldos', [SuperadminDatabaseBackupController::class, 'index'])->name('respaldos.index');
        Route::post('/respaldos', [SuperadminDatabaseBackupController::class, 'store'])->middleware(['password.confirm', 'throttle:10,1'])->name('respaldos.store');
        Route::get('/respaldos/{backup}/descargar', [SuperadminDatabaseBackupController::class, 'download'])->middleware(['password.confirm', 'throttle:10,1'])->name('respaldos.download');
        Route::post('/respaldos/{backup}/verificar', [SuperadminDatabaseBackupController::class, 'verify'])->middleware(['password.confirm', 'throttle:10,1'])->name('respaldos.verify');
    });

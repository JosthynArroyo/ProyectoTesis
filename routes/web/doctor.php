<?php

use App\Http\Controllers\CitaController;
use App\Http\Controllers\CitaPrioridadController;
use App\Http\Controllers\Doctor\AdminController as DoctorDashboardController;
use App\Http\Controllers\Doctor\CertificadoMedicoController as DoctorCertificadoMedicoController;
use App\Http\Controllers\Doctor\HistorialController as DoctorHistorialController;
use App\Http\Controllers\Doctor\HorarioController as DoctorHorarioController;
use App\Http\Controllers\Doctor\PedidoLaboratorioController;
use App\Http\Controllers\Doctor\RecetaController;
use App\Http\Controllers\Doctor\SoapController as DoctorSoapController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/certificados/{certificado}/corregir', [DoctorCertificadoMedicoController::class, 'corregir'])
        ->name('doctor.certificados.corregir');
    Route::post('/certificados/{certificado}/corregir', [DoctorCertificadoMedicoController::class, 'storeCorregido'])
        ->name('doctor.certificados.store-corregido');

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

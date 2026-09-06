<?php

use App\Http\Controllers\CitaComprobanteController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\DependienteController;
use App\Http\Controllers\Doctor\RecetaController;
use App\Http\Controllers\Paciente\AdminController as PacienteDashboardController;
use App\Http\Controllers\Paciente\CertificadoMedicoController as PacienteCertificadoMedicoController;
use App\Http\Controllers\Paciente\HistorialController as PacienteHistorialController;
use App\Http\Controllers\Paciente\LaboratorioController as PacienteLaboratorioController;
use App\Http\Controllers\Paciente\PagoController as PacientePagoController;
use Illuminate\Support\Facades\Route;

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

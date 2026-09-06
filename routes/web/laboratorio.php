<?php

use App\Http\Controllers\Laboratorio\AdminController as LaboratorioDashboardController;
use App\Http\Controllers\Laboratorio\HorarioController as LaboratorioHorarioController;
use App\Http\Controllers\Laboratorio\OrdenController as LaboratorioOrdenController;
use App\Http\Controllers\Laboratorio\PedidoLaboratorioController as LaboratorioPedidoLaboratorioController;
use App\Http\Controllers\Laboratorio\PedidoLaboratorioResultadoController as LaboratorioPedidoResultadoController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/pedidos-mvp', [LaboratorioPedidoLaboratorioController::class, 'index'])->name('pedidos.index');
    Route::post('/pedidos-mvp/{pedido}/muestra', [LaboratorioPedidoLaboratorioController::class, 'marcarMuestra'])->name('pedidos.muestra');
    Route::post('/pedidos-mvp/{pedido}/resultado', [LaboratorioPedidoLaboratorioController::class, 'subirResultado'])->name('pedidos.resultado');
    Route::get('/pedidos-mvp/{pedido}/download-orden', [LaboratorioPedidoLaboratorioController::class, 'downloadOrden'])->name('pedidos.download-orden');
    Route::get('/pedidos-mvp/{pedido}/descargar-orden', [LaboratorioPedidoLaboratorioController::class, 'downloadOrdenAttachment'])->name('pedidos.descargar-orden');
    Route::get('/pedidos-mvp/{pedido}/download-resultado', [LaboratorioPedidoLaboratorioController::class, 'downloadResultado'])->name('pedidos.download-resultado');

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
});

<?php

use App\Http\Controllers\DemoAccessController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

// ======================= ACCESO OFICIAL DEMO =======================
Route::get('/demo/clinica', [PublicPageController::class, 'clinicWelcome'])->name('demo.clinic');
Route::get('/demo/acceso', [DemoAccessController::class, 'selector'])->name('demo.access.selector');
Route::get('/demo/acceso/{role}', [DemoAccessController::class, 'loginAsRole'])->name('demo.access.role');

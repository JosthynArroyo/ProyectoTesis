<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Services\DashboardAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function dashboard(Request $request, DashboardAnalyticsService $analytics)
    {
        $user = Auth::user();
        $dashboard = $analytics->buildLaboratorioDashboard($user, $request->all());
        $metrics = $dashboard['metrics'];
        $ordenesRecientes = collect($dashboard['lists']['recent_orders'] ?? []);

        return view('laboratorio.dashboard', compact(
            'user',
            'dashboard',
            'metrics',
            'ordenesRecientes'
        ))->with([
            'citasHoy' => $metrics['received_today']['value'] ?? 0,
            'ordenesPendientes' => ($metrics['pending']['value'] ?? 0) + ($metrics['in_process']['value'] ?? 0),
            'pedidosPendientes' => $metrics['pending']['value'] ?? 0,
            'resultadosHoy' => $metrics['completed']['value'] ?? 0,
        ]);
    }

    public function dashboardData(Request $request, DashboardAnalyticsService $analytics)
    {
        $user = $request->user();
        return response()->json($analytics->buildLaboratorioDashboard($user, $request->all()));
    }
}

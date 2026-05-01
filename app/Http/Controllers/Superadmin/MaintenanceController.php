<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceAccessService;
use App\Services\SiteSettingsService;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function edit(Request $request, SiteSettingsService $settings, MaintenanceAccessService $maintenanceAccess)
    {
        $values = $settings->getMany([
            'maintenance.enabled',
            'maintenance.message',
            'maintenance.until',
            'maintenance.allow_ips',
        ]);

        return view('superadmin.maintenance', [
            'settings' => $values,
            'detectedIps' => $maintenanceAccess->requestIps($request),
        ]);
    }

    public function update(Request $request, SiteSettingsService $settings)
    {
        $data = $request->validate([
            'maintenance_enabled' => ['required', 'boolean'],
            'maintenance_message' => ['required', 'string', 'max:240'],
            'maintenance_until' => ['required', 'date'],
            'maintenance_allow_ips' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = [
            'maintenance.enabled' => $request->boolean('maintenance_enabled'),
            'maintenance.message' => $data['maintenance_message'] ?? null,
            'maintenance.until' => $data['maintenance_until'] ?? null,
            'maintenance.allow_ips' => $data['maintenance_allow_ips'] ?? null,
        ];

        $settings->setMany($payload, [
            'maintenance.enabled' => ['section' => 'maintenance', 'type' => 'boolean'],
            'maintenance.message' => ['section' => 'maintenance', 'type' => 'text'],
            'maintenance.until' => ['section' => 'maintenance', 'type' => 'text'],
            'maintenance.allow_ips' => ['section' => 'maintenance', 'type' => 'text'],
        ]);

        return back()->with('success', 'Configuracion de mantenimiento actualizada.');
    }
}

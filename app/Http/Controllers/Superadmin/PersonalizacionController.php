<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingWelcomeRequest;
use App\Http\Requests\PersonalizacionServiciosRequest;
use App\Models\Especialidad;
use App\Services\LandingWelcomeManager;
use App\Services\LandingWelcomeService;

class PersonalizacionController extends Controller
{
    public function edit(LandingWelcomeService $welcome)
    {
        $especialidadesActivas = \App\Models\Especialidad::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('superadmin.personalizacion.bienvenida', [
            'settings' => $welcome->all(),
            'stats' => $welcome->stats(),
            'slides' => $welcome->slides(),
            'cards' => $welcome->infoCards(),
            'doctors' => $welcome->doctors(),
            'prices' => $welcome->prices(),
            'featuredIds' => $welcome->featuredSpecialtyIds(),
            'especialidadesActivas' => $especialidadesActivas,
        ]);
    }

    public function update(LandingWelcomeRequest $request, LandingWelcomeManager $manager)
    {
        $manager->update($request);

        return back()->with('success', 'Bienvenida actualizada correctamente.');
    }

    public function serviciosEdit()
    {
        $especialidades = Especialidad::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('superadmin.personalizacion.servicios', compact('especialidades'));
    }

    public function serviciosUpdate(PersonalizacionServiciosRequest $request)
    {
        $data = $request->validated();

        foreach (($data['especialidades'] ?? []) as $id => $payload) {
            $especialidad = Especialidad::query()->find($id);
            if (! $especialidad) {
                continue;
            }

            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;
            $icono = $icono === '' ? null : $icono;

            $especialidad->nombre = $payload['nombre'];
            $especialidad->descripcion = $payload['descripcion'] ?? null;
            $especialidad->icono = $icono;
            $especialidad->activo = ! empty($payload['activo']);
            $especialidad->orden = (int) ($payload['orden'] ?? 0);
            $especialidad->save();
        }

        foreach (($data['nuevas'] ?? []) as $payload) {
            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;
            $icono = $icono === '' ? null : $icono;

            Especialidad::query()->create([
                'nombre' => $payload['nombre'],
                'descripcion' => $payload['descripcion'] ?? null,
                'icono' => $icono,
                'activo' => ! empty($payload['activo']),
                'orden' => (int) ($payload['orden'] ?? 0),
            ]);
        }

        return back()->with('success', 'Servicios actualizados correctamente.');
    }
}

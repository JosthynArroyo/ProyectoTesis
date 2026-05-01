<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Services\LandingWelcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function welcome(LandingWelcomeService $welcome): View
    {
        $featured = $welcome->featuredSpecialties();

        $especialidadesDestacadas = ! empty($featured)
            ? collect($featured)
            : Especialidad::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->limit(3)
                ->get();

        return view('welcome', compact('especialidadesDestacadas'));
    }

    public function home(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect('/');
        }

        return redirect($user->dashboardPath());
    }

    public function servicios(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $tipo = strtolower(trim((string) $request->query('tipo', 'all')));
        $allowedTypes = ['all', 'general', 'especialidad', 'diagnostico', 'procedimiento'];
        if (! in_array($tipo, $allowedTypes, true)) {
            $tipo = 'all';
        }

        $especialidades = Especialidad::query()
            ->where('activo', true)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('nombre', 'like', $like)
                        ->orWhere('descripcion', 'like', $like);
                });
            })
            ->when($tipo !== 'all', fn ($query) => $this->applyServiceTypeFilter($query, $tipo))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(12)
            ->withQueryString();

        $serviceTypes = [
            'all' => 'Todos',
            'general' => 'General',
            'especialidad' => 'Especialidades',
            'diagnostico' => 'Diagnostico',
            'procedimiento' => 'Procedimientos',
        ];

        return view('servicios', compact('especialidades', 'q', 'tipo', 'serviceTypes'));
    }

    private function applyServiceTypeFilter($query, string $tipo): void
    {
        $nonSpecialtyMatches = ['laboratorio', 'odontolog'];

        match ($tipo) {
            'general' => $query->whereRaw('LOWER(nombre) = ?', ['medicina general']),
            'diagnostico' => $query->whereRaw('LOWER(nombre) like ?', ['%laboratorio%']),
            'procedimiento' => $query->whereRaw('LOWER(nombre) like ?', ['%odontolog%']),
            'especialidad' => $query
                ->whereRaw('LOWER(nombre) <> ?', ['medicina general'])
                ->where(function ($inner) use ($nonSpecialtyMatches) {
                    foreach ($nonSpecialtyMatches as $match) {
                        $inner->whereRaw('LOWER(nombre) not like ?', ['%'.$match.'%']);
                    }
                }),
            default => null,
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user() || $request->isMethod('post')) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/');
    }
}

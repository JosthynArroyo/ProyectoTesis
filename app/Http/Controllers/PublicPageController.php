<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Services\ApplicationModeService;
use App\Services\LandingWelcomeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function __construct(
        private readonly ApplicationModeService $applicationMode
    ) {}

    /**
     * Serve root endpoint: commercial landing in demo mode, real clinic page in production.
     */
    public function welcome(LandingWelcomeService $welcome): View
    {
        if ($this->applicationMode->isDemo()) {
            return view('landing.commercial');
        }

        return $this->clinicWelcome($welcome);
    }

    /**
     * Serve the public clinic page (Welcome). In production, this is the main page.
     * In demo mode, this is accessible via /demo/clinica as part of the preview flow.
     */
    public function clinicWelcome(LandingWelcomeService $welcome): View
    {
        if (request()->routeIs('demo.*') && ! $this->applicationMode->isDemo()) {
            abort(404);
        }

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
        $q = $this->normalizeSearchTerm($request->query('q', ''));
        $tipo = strtolower(trim((string) $request->query('tipo', 'all')));
        $allowedTypes = ['all', 'general', 'especialidad', 'diagnostico', 'procedimiento'];
        if (! in_array($tipo, $allowedTypes, true)) {
            $tipo = 'all';
        }

        $hasActive = Especialidad::query()->where('activo', true)->exists();

        $especialidades = Especialidad::query()
            ->when($hasActive, fn ($query) => $query->where('activo', true))
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
            // Keep these raw fragments static and parameterized; no request SQL belongs here.
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
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($this->applicationMode->isDemo()) {
            return redirect()->route('demo.access.selector');
        }

        return redirect('/');
    }

    private function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }
}

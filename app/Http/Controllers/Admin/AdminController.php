<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use App\Models\User;
use App\Services\Admin\AdminDashboardReportService;
use App\Services\Admin\AdminUserManagementService;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfileAvatarService;
use App\Support\ImageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function __construct(
        protected AdminDashboardReportService $dashboardService,
        protected AdminUserManagementService $userService
    ) {}

    // ===== Dashboard =====
    public function dashboard(Request $request, DashboardAnalyticsService $analytics)
    {
        $data = $this->dashboardService->dashboard($request, Auth::user(), $analytics);

        return view('admin.dashboard', $data['viewData'])->with($data['withData']);
    }

    public function resumenGlobal(Request $request)
    {
        $result = $this->dashboardService->resumenGlobal($request);

        if (is_array($result) && ! empty($result['redirect'])) {
            return redirect($result['redirect']);
        }

        return $result;
    }

    public function dashboardData(Request $request, DashboardAnalyticsService $analytics): JsonResponse
    {
        return $this->dashboardService->dashboardData($request, $analytics);
    }

    public function exportPdf(Request $request)
    {
        return $this->dashboardService->exportPdf($request);
    }

    // ===== Perfil =====
    public function editarPerfil()
    {
        $data = $this->dashboardService->editarPerfil(Auth::user());

        return view('admin.perfil', $data);
    }

    public function actualizarPerfil(Request $request, ProfileAvatarService $profileAvatars)
    {
        $result = $this->dashboardService->actualizarPerfil($request, Auth::user(), $profileAvatars);

        if (! $result['ok']) {
            return back()->withErrors($result['errors'])->withInput();
        }

        return redirect()->route('admin.perfil.edit')->with('success', 'Perfil actualizado correctamente.');
    }

    // ===== Usuarios (listado + filtros) =====
    public function usuarios(Request $request)
    {
        $data = $this->userService->usuarios($request);
        $role = $data['role'];
        unset($data['role']);

        return view('admin.usuarios', $data)->with('role', $role);
    }

    public function checkEmail(Request $request): JsonResponse
    {
        return $this->userService->checkEmail($request);
    }

    // ===== CRUD usuario rápido =====
    public function usuariosCreate()
    {
        $data = $this->userService->usuariosCreate();

        return view('admin.users.create', $data);
    }

    public function usuariosStore(Request $request)
    {
        $result = $this->userService->usuariosStore($request);

        if (! $result['ok']) {
            return back()->withErrors($result['errors'])->withInput();
        }

        return redirect()->route('admin.usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function usuariosShow(User $user)
    {
        $result = $this->userService->usuariosShow($user);

        if (! $result['ok']) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors([$result['message']]);
        }

        return view('admin.users.show', ['user' => $result['user']]);
    }

    public function usuariosEdit(User $user)
    {
        $result = $this->userService->usuariosEdit($user);

        if (! $result['ok']) {
            return redirect()->route('admin.usuarios.index')
                ->withErrors([$result['message']]);
        }

        return view('admin.users.edit', [
            'user' => $result['user'],
            'roles' => $result['roles'],
            'especialidades' => $result['especialidades'],
        ]);
    }

    public function usuariosUpdate(Request $request, User $user)
    {
        $result = $this->userService->usuariosUpdate($request, $user);

        if (! $result['ok']) {
            if (($result['type'] ?? '') === 'flash') {
                return redirect()->route('admin.usuarios.index')
                    ->withErrors([$result['message']]);
            }

            return back()->withErrors($result['errors'])->withInput();
        }

        return redirect()
            ->route('admin.usuarios.edit', $user)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function usuariosDestroy(User $user)
    {
        $result = $this->userService->usuariosDestroy($user, (int) Auth::id());

        if (! $result['ok']) {
            return back()->withErrors([$result['message']]);
        }

        return back()->with('success', 'Usuario eliminado.');
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad, ImageUrl $imageUrl): JsonResponse
    {
        return $this->userService->doctoresPorEspecialidad($especialidad, $imageUrl);
    }

    // ===== Exportes (respetan buscar + role) =====
    public function usuariosExportExcel(Request $request)
    {
        $result = $this->userService->usuariosExportExcel($request);

        if (is_array($result) && ! $result['ok']) {
            return back()->withErrors([$result['message']]);
        }

        return $result;
    }

    public function usuariosExportPdf(Request $request)
    {
        $result = $this->userService->usuariosExportPdf($request);

        if (is_array($result) && ! $result['ok']) {
            return back()->withErrors([$result['message']]);
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Compatibility Proxies
    |--------------------------------------------------------------------------
    */

    protected function extractPatientFlags(Request $request): array
    {
        return $this->userService->extractPatientFlags($request);
    }

    protected function resolveAvatarFolder(User $user): string
    {
        return $this->userService->resolveAvatarFolder($user);
    }

    protected function isPrivilegedRole(?string $roleName): bool
    {
        return $this->userService->isPrivilegedRole($roleName);
    }

    protected function isPrivilegedAccount(User $user): bool
    {
        return $this->userService->isPrivilegedAccount($user);
    }

    protected function isClinicalProfessionalAccount(User $user): bool
    {
        return $this->userService->isClinicalProfessionalAccount($user);
    }

    protected function userHasClinicalOrAdministrativeRecords(User $user): bool
    {
        return $this->userService->userHasClinicalOrAdministrativeRecords($user);
    }

    protected function loadPdfCss(string $relativePath): string
    {
        return $this->userService->loadPdfCss($relativePath);
    }

    protected function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return $this->userService->normalizeSearchTerm($value, $maxLength);
    }

    protected function normalizePerPage(mixed $value, int $default = 15): int
    {
        return $this->userService->normalizePerPage($value, $default);
    }

    protected function normalizeUserRoleFilter(string $role): string
    {
        return $this->userService->normalizeUserRoleFilter($role);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Role;
use App\Models\Pago;
use App\Models\Horario;
use App\Models\Dependiente;
use App\Models\NotaSoap;
use App\Models\Receta;
use App\Models\PedidoLaboratorio;
use App\Models\Factura;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class DemoDashboardController extends Controller
{
    public function index()
    {
        return view('demo.index', array_merge(
            $this->baseData('landing', [
                'showRoleSwitcher' => false,
                'headerTitle' => 'Demo del sistema',
                'headerSubtitle' => 'Explora los paneles publicos con datos simulados y acciones bloqueadas.',
            ]),
            [
                'roleCards' => $this->roleCards(),
            ]
        ));
    }

    public function superadminDashboard()
    {
        return $this->superadminView('demo.superadmin.dashboard', 'dashboard');
    }

    public function superadminAdministradores()
    {
        $buscar = request()->query('buscar', '');
        $perPage = (int) request()->query('per_page', 12);
        if (!in_array($perPage, [12, 24, 48])) {
            $perPage = 12;
        }

        $simulatedAction = request()->query('simulated_action');
        if ($simulatedAction) {
            $name = request()->query('simulated_name', 'Administrador');
            if ($simulatedAction === 'suspended') {
                $adminId = (int) request()->query('admin_id');
                $superadminData = $this->superadminData();
                $found = collect($superadminData['administradores'])->firstWhere('id', $adminId);
                $name = $found['name'] ?? 'Administrador';
                session()->flash('success', "Se guardó la suspensión de {$name} (Simulado).");
            } elseif ($simulatedAction === 'deleted') {
                session()->flash('success', "Se eliminó la cuenta de {$name} (Simulado).");
            } elseif ($simulatedAction === 'blocked') {
                session()->flash('success', "Se bloqueó el acceso de {$name} (Simulado).");
            } elseif ($simulatedAction === 'deactivated') {
                session()->flash('success', "La cuenta de {$name} quedará inactiva (Simulado).");
            } elseif ($simulatedAction === 'activated') {
                session()->flash('success', "Se restauró el acceso de {$name} (Simulado).");
            }
            return redirect()->route('demo.superadmin.administradores');
        }

        $superadminData = $this->superadminData();
        $admins = collect($superadminData['administradores']);

        if ($buscar !== '') {
            $buscarLower = strtolower($buscar);
            $admins = $admins->filter(function ($admin) use ($buscarLower) {
                return str_contains(strtolower($admin['name']), $buscarLower)
                    || str_contains(strtolower($admin['email']), $buscarLower)
                    || str_contains((string) $admin['id'], $buscarLower);
            });
        }

        $total = $admins->count();
        $page = (int) request()->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;
        $items = $admins->slice($offset, $perPage)->values()->all();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return view('demo.superadmin.administradores', array_merge(
            $this->baseData('superadmin', ['activeSidebarKey' => 'administradores']),
            $superadminData,
            [
                'admins' => $paginated,
                'buscar' => $buscar,
                'perPage' => $perPage,
            ]
        ));
    }

    public function superadminUsuarios()
    {
        $buscar = request()->query('buscar', '');
        $role = request()->query('role', 'all');

        $superadminData = $this->superadminData();
        $usersCollection = collect($superadminData['usuarios']);

        if ($role !== 'all') {
            $usersCollection = $usersCollection->filter(function ($u) use ($role) {
                return strtolower($u['role']) === strtolower($role);
            });
        }

        if ($buscar !== '') {
            $buscarLower = strtolower($buscar);
            $usersCollection = $usersCollection->filter(function ($u) use ($buscarLower) {
                return str_contains(strtolower($u['name']), $buscarLower)
                    || str_contains(strtolower($u['email']), $buscarLower)
                    || str_contains(strtolower($u['dni']), $buscarLower);
            });
        }

        $total = $usersCollection->count();
        $perPage = 20; // Replicating the real pagination of 20 per page
        $page = (int) request()->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;
        $items = $usersCollection->slice($offset, $perPage)->values()->all();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return view('demo.superadmin.usuarios', array_merge(
            $this->baseData('superadmin', ['activeSidebarKey' => 'usuarios']),
            $superadminData,
            [
                'users' => $paginated,
                'search' => $buscar,
                'role' => $role,
            ]
        ));
    }

    public function superadminSolicitudes()
    {
        $status = request()->query('status', 'all');

        // Handle simulated actions: only show a one-time flash, NO session persistence.
        // State always resets to fixed initial data on every page load.
        $simulatedAction = request()->query('simulated_action');
        if ($simulatedAction) {
            if ($simulatedAction === 'approved') {
                session()->flash('success', 'Se aprobó el acceso de personalización (Simulado). Los datos se restablecen al recargar.');
            } elseif ($simulatedAction === 'rejected') {
                session()->flash('success', 'Se rechazó la solicitud (Simulado). Los datos se restablecen al recargar.');
            } elseif ($simulatedAction === 'revoked') {
                session()->flash('success', 'Se revocó el acceso de personalización (Simulado). Los datos se restablecen al recargar.');
            }

            return redirect()->route('demo.superadmin.solicitudes');
        }

        $superadminData = $this->superadminData();

        // Always use fixed initial data — no session reads, no persistence.
        $solicitudes = collect($superadminData['solicitudes']);

        if ($status !== 'all') {
            $solicitudes = $solicitudes->filter(function ($sol) use ($status) {
                return $sol['status'] === $status;
            });
        }

        $total = $solicitudes->count();
        $perPage = 10;
        $page = (int) request()->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;
        $items = $solicitudes->slice($offset, $perPage)->values()->all();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        // pendingCount always computed from fixed data, never from session.
        $pendingCount = collect($superadminData['solicitudes'])
            ->where('status', 'pending')
            ->count();

        return view('demo.superadmin.solicitudes', array_merge(
            $this->baseData('superadmin', [
                'activeSidebarKey' => 'solicitudes',
                'pendingPersonalizacion' => $pendingCount,
            ]),
            $superadminData,
            [
                'requests' => $paginated,
                'status' => $status,
                'pendingPersonalizacion' => $pendingCount,
            ]
        ));
    }


    public function superadminPersonalizacion()
    {
        return $this->superadminView('demo.superadmin.personalizacion', 'personalizacion');
    }

    public function superadminMantenimiento()
    {
        return $this->superadminView('demo.superadmin.mantenimiento', 'mantenimiento');
    }

    public function superadminRespaldos()
    {
        $type = request()->query('type');
        $status = request()->query('status');
        $date = request()->query('date');

        $simulatedAction = request()->query('simulated_action');
        if ($simulatedAction) {
            if ($simulatedAction === 'created') {
                session()->flash('success', 'Se programó el respaldo manual (Simulado). Los datos de la demo se restablecen al recargar.');
            } elseif ($simulatedAction === 'verified') {
                session()->flash('success', 'Se verificó la integridad del archivo de respaldo (Simulado).');
            } elseif ($simulatedAction === 'downloaded') {
                session()->flash('success', 'Descarga de respaldo cifrado iniciada (Simulado).');
            }

            return redirect()->route('demo.superadmin.respaldos');
        }

        $superadminData = $this->superadminData();
        $backupsCollection = collect($superadminData['respaldos']);

        if (!empty($type)) {
            $backupsCollection = $backupsCollection->filter(fn($b) => $b['type'] === $type);
        }
        if (!empty($status)) {
            $backupsCollection = $backupsCollection->filter(fn($b) => $b['status'] === $status);
        }
        if (!empty($date)) {
            $backupsCollection = $backupsCollection->filter(fn($b) => str_contains($b['created_at'], $date));
        }

        $total = $backupsCollection->count();
        $perPage = 10;
        $page = (int) request()->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;
        $items = $backupsCollection->slice($offset, $perPage)->values()->all();

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $pendingCount = collect($superadminData['solicitudes'])
            ->where('status', 'pending')
            ->count();

        return view('demo.superadmin.respaldos', array_merge(
            $this->baseData('superadmin', [
                'activeSidebarKey' => 'respaldos',
                'pendingPersonalizacion' => $pendingCount,
            ]),
            $superadminData,
            [
                'backups' => $paginated,
                'filters' => [
                    'type' => $type,
                    'status' => $status,
                    'date' => $date,
                ],
                'lastSuccessfulDate' => \Illuminate\Support\Carbon::parse('2026-08-08 02:00:00'),
                'isOverdue' => false,
                'hasPendingOrProcessing' => false,
            ]
        ));
    }

    public function adminDashboard()
    {
        return $this->adminView('demo.admin.dashboard', 'dashboard');
    }


    public function adminRegistrarUsuario()
    {
        return $this->adminView('demo.admin.registrar-usuario', 'registrar-usuario');
    }

    public function adminPersonalizacion()
    {
        return $this->adminView('demo.admin.personalizacion', 'personalizacion');
    }

    public function adminHistorialClinico()
    {
        return $this->adminView('demo.admin.historial-clinico', 'historial-clinico');
    }

    public function adminHorarios()
    {
        return $this->adminView('demo.admin.horarios', 'horarios');
    }

    public function adminRecordatorios()
    {
        return $this->adminView('demo.admin.recordatorios', 'recordatorios');
    }

    public function adminAgendarManualmente()
    {
        return $this->adminView('demo.admin.agendar-manualmente', 'agendar-manualmente');
    }

    public function adminCambiosCitas()
    {
        return $this->adminView('demo.admin.cambios-citas', 'cambios-citas');
    }

    public function adminGestionPagos()
    {
        return $this->adminView('demo.admin.gestion-pagos', 'gestion-pagos');
    }

    public function adminPerfil()
    {
        return $this->adminView('demo.admin.perfil', 'perfil');
    }

    public function adminNotificacionesContacto()
    {
        return $this->adminView('demo.admin.notificaciones-contacto', 'notificaciones-contacto');
    }

    public function pacienteDashboard()
    {
        return $this->pacienteView('demo.paciente.dashboard', 'dashboard');
    }

    public function pacienteCitas()
    {
        return $this->pacienteView('demo.paciente.citas', 'citas');
    }

    public function pacientePagos()
    {
        return $this->pacienteView('demo.paciente.pagos', 'pagos');
    }

    public function pacienteHistorialClinico()
    {
        return $this->pacienteView('demo.paciente.historial-clinico', 'historial-clinico');
    }

    public function pacienteResultados()
    {
        return $this->pacienteView('demo.paciente.resultados', 'resultados');
    }

    public function pacienteAgendarCita()
    {
        return $this->pacienteView('demo.paciente.agendar-cita', 'agendar-cita');
    }

    public function pacientePerfil()
    {
        return $this->pacienteView('demo.paciente.perfil', 'perfil');
    }

    public function doctorDashboard()
    {
        return $this->doctorView('demo.doctor.dashboard', 'dashboard');
    }

    public function doctorDashboardData(Request $request)
    {
        $state = $this->getDemoState('doctor');
        return response()->json($state['dashboard']);
    }

    public function doctorCitas()
    {
        return $this->doctorView('demo.doctor.citas', 'citas');
    }

    public function doctorPacientes()
    {
        return $this->doctorView('demo.doctor.pacientes', 'pacientes');
    }

    public function doctorHistorialRecetas()
    {
        return $this->doctorView('demo.doctor.historial-recetas', 'historial-recetas');
    }

    public function doctorAgendaSemanal()
    {
        return $this->doctorView('demo.doctor.agenda-semanal', 'agenda-semanal');
    }

    public function doctorMiHorario()
    {
        return $this->doctorView('demo.doctor.mi-horario', 'mi-horario');
    }

    public function doctorPerfil()
    {
        return $this->doctorView('demo.doctor.perfil', 'perfil');
    }

    public function laboratorioDashboard()
    {
        return $this->laboratorioView('demo.laboratorio.dashboard', 'dashboard');
    }

    public function laboratorioCitasResultados()
    {
        return $this->laboratorioView('demo.laboratorio.citas-resultados', 'citas-resultados');
    }

    public function laboratorioHorarios()
    {
        return $this->laboratorioView('demo.laboratorio.horarios', 'horarios');
    }

    // ==========================================
    // SIMULATED ACTIONS AND SUB-ROUTES FOR DEMO
    // ==========================================

    // Admin Action Handlers
    public function adminDashboardData()
    {
        return response()->json([
            'filters' => ['period' => '30d'],
            'charts' => $this->getDemoState('admin')['charts'] ?? []
        ]);
    }

    public function adminDashboardResumen()
    {
        $state = $this->getDemoState('admin');
        $citas = collect($state['recentAppointments'] ?? []);
        return response()->json([
            'agendadas' => $citas->count(),
            'completadas' => $citas->where('status', 'Realizada')->count(),
            'canceladas' => $citas->where('status', 'Cancelada')->count(),
        ]);
    }

    public function adminUsuarios(Request $request)
    {
        $state = $this->getDemoState('admin');
        $buscar = strtolower($request->get('buscar', ''));
        $role = strtolower($request->get('role', 'all'));
        $perPage = (int) $request->get('per_page', 12);

        $usuarios = collect($state['usuarios']);

        if ($role !== 'all') {
            $usuarios = $usuarios->filter(fn($u) => strtolower($u['role']) === $role);
        }
        if ($buscar !== '') {
            $usuarios = $usuarios->filter(fn($u) => str_contains(strtolower($u['name']), $buscar) || str_contains(strtolower($u['email']), $buscar) || str_contains(strtolower($u['dni']), $buscar));
        }

        $allColumns = ['usuario', 'contacto', 'rol', 'estado', 'especialidades', 'acciones'];
        $cols = $request->get('cols', $allColumns);

        $mappedUsers = $usuarios->map(fn($u) => $this->mapUser($u))->values();

        $page = (int) $request->get('page', 1);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $mappedUsers->forPage($page, $perPage)->values(),
            $mappedUsers->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('demo.admin.usuarios', [
            'users' => $paginated,
            'buscar' => $request->get('buscar', ''),
            'perPage' => $perPage,
            'allColumns' => $allColumns,
            'cols' => $cols,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'usuarios']));
    }

    public function adminUsuariosStore(Request $request)
    {
        $state = $this->getDemoState('admin');
        $id = count($state['usuarios']) + 100;
        $newUser = [
            'id' => $id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => ucfirst($request->input('role', 'Paciente')),
            'role_tone' => $request->input('role') === 'doctor' ? 'info' : 'success',
            'status' => 'active',
            'status_tone' => 'success',
            'specialty' => $request->input('especialidad', 'Sin especialidad'),
            'last_login' => 'N/D',
            'registered_at' => now()->format('d/m/Y'),
            'telefono' => $request->input('telefono'),
            'dni' => $request->input('dni'),
        ];
        $state['usuarios'][] = $newUser;
        $this->setDemoState('admin', $state);

        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario registrado correctamente (Simulado).');
    }

    public function adminUsuariosShow($id)
    {
        $state = $this->getDemoState('admin');
        $uData = collect($state['usuarios'])->firstWhere('id', $id);
        if (!$uData) abort(404);
        
        $u = $this->mapUser($uData);
        return view('demo.admin.usuarios-show', [
            'u' => $u,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'usuarios']));
    }

    public function adminUsuariosEdit($id)
    {
        $state = $this->getDemoState('admin');
        $uData = collect($state['usuarios'])->firstWhere('id', $id);
        if (!$uData) abort(404);

        $u = $this->mapUser($uData);
        return view('demo.admin.usuarios-edit', [
            'u' => $u,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'usuarios']));
    }

    public function adminUsuariosUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['usuarios'] as &$u) {
            if ($u['id'] == $id) {
                $u['name'] = $request->input('name');
                $u['email'] = $request->input('email');
                $u['telefono'] = $request->input('telefono');
                $u['dni'] = $request->input('dni');
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario actualizado correctamente (Simulado).');
    }

    public function adminUsuariosDestroy($id)
    {
        $state = $this->getDemoState('admin');
        $state['usuarios'] = array_values(array_filter($state['usuarios'], fn($u) => $u['id'] != $id));
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario eliminado correctamente (Simulado).');
    }

    public function adminUsuariosBlock($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['usuarios'] as &$u) {
            if ($u['id'] == $id) {
                $u['status'] = 'blocked';
                $u['status_tone'] = 'danger';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario bloqueado (Simulado).');
    }

    public function adminUsuariosSuspend(Request $request, $id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['usuarios'] as &$u) {
            if ($u['id'] == $id) {
                $u['status'] = 'suspended';
                $u['status_tone'] = 'warning';
                $u['suspended_until'] = $request->input('until');
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario suspendido (Simulado).');
    }

    public function adminUsuariosActivate($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['usuarios'] as &$u) {
            if ($u['id'] == $id) {
                $u['status'] = 'active';
                $u['status_tone'] = 'success';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario reactivado (Simulado).');
    }

    public function adminUsuariosDeactivate($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['usuarios'] as &$u) {
            if ($u['id'] == $id) {
                $u['status'] = 'inactive';
                $u['status_tone'] = 'warning';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.usuarios.index')->with('success', 'Usuario desactivado (Simulado).');
    }

    // Personalizacion
    public function adminPersonalizacionRequestAccess()
    {
        session()->flash('success', 'Solicitud de acceso enviada al superadmin (Simulado).');
        return redirect()->back();
    }

    public function adminPersonalizacionBienvenidaEdit()
    {
        return $this->adminView('demo.admin.personalizacion-bienvenida', 'personalizacion');
    }

    public function adminPersonalizacionBienvenidaUpdate(Request $request)
    {
        session()->flash('success', 'Sección de bienvenida actualizada correctamente (Simulado).');
        return redirect()->route('demo.admin.personalizacion.index');
    }

    public function adminPersonalizacionServiciosEdit()
    {
        return $this->adminView('demo.admin.personalizacion-servicios', 'personalizacion');
    }

    public function adminPersonalizacionServiciosUpdate(Request $request)
    {
        session()->flash('success', 'Sección de servicios actualizada correctamente (Simulado).');
        return redirect()->route('demo.admin.personalizacion.index');
    }

    public function adminPersonalizacionContactoEdit()
    {
        return $this->adminView('demo.admin.personalizacion-contacto', 'personalizacion');
    }

    public function adminPersonalizacionContactoUpdate(Request $request)
    {
        session()->flash('success', 'Sección de contacto actualizada correctamente (Simulado).');
        return redirect()->route('demo.admin.personalizacion.index');
    }

    // Historial
    public function adminHistorialPaciente($id)
    {
        return $this->adminView('demo.admin.historial-paciente', 'historial-clinico');
    }

    public function adminHistorialShow($id)
    {
        return $this->adminView('demo.admin.historial-show', 'historial-clinico');
    }

    // Horarios
    public function adminHorariosCreate()
    {
        return $this->adminView('demo.admin.horarios-crear', 'horarios');
    }

    public function adminHorariosStore(Request $request)
    {
        $state = $this->getDemoState('admin');
        $id = count($state['horarios']) + 1;
        $state['horarios'][] = [
            'id' => $id,
            'day' => $request->input('day', 'Lunes'),
            'range' => $request->input('range', '08:00 - 17:00'),
            'note' => $request->input('note', 'Operación normal'),
        ];
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.horarios.index')->with('success', 'Horario guardado correctamente (Simulado).');
    }

    public function adminHorariosEdit($id)
    {
        $state = $this->getDemoState('admin');
        $horario = collect($state['horarios'])->firstWhere('id', $id);
        if (!$horario) abort(404);
        return view('demo.admin.horarios-editar', [
            'horario' => (object) $horario,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'horarios']));
    }

    public function adminHorariosUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['horarios'] as &$h) {
            if ($h['id'] == $id) {
                $h['range'] = $request->input('range');
                $h['note'] = $request->input('note');
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.horarios.index')->with('success', 'Horario actualizado correctamente (Simulado).');
    }

    public function adminHorariosDestroy($id)
    {
        $state = $this->getDemoState('admin');
        $state['horarios'] = array_values(array_filter($state['horarios'], fn($h) => $h['id'] != $id));
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.horarios.index')->with('success', 'Horario eliminado (Simulado).');
    }

    // Recordatorios
    public function adminRecordatoriosEnviado($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['recordatorios'] as &$r) {
            if ($r['id'] == $id) {
                $r['status'] = 'Enviado';
                $r['status_tone'] = 'success';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.recordatorios.index')->with('success', 'Recordatorio marcado como enviado (Simulado).');
    }

    public function adminRecordatoriosOmitido($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['recordatorios'] as &$r) {
            if ($r['id'] == $id) {
                $r['status'] = 'Omitido';
                $r['status_tone'] = 'neutral';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.recordatorios.index')->with('success', 'Recordatorio omitido (Simulado).');
    }

    // Agendar manualmente
    public function adminAgendarManualmenteStore(Request $request)
    {
        $state = $this->getDemoState('admin');
        $id = count($state['recentAppointments']) + 1;
        $state['recentAppointments'][] = [
            'id' => $id,
            'time' => $request->input('hora', '08:00'),
            'patient' => 'Paciente Registrado',
            'doctor' => 'Dra. Sofia Cardenas',
            'specialty' => 'Pediatria',
            'status' => 'Confirmada',
            'status_tone' => 'info',
            'fecha' => $request->input('fecha', now()->toDateString()),
            'hora' => $request->input('hora', '08:00'),
            'prioridad_nivel' => 'MEDIA',
            'prioridad_red_flag' => false,
        ];
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.dashboard')->with('success', 'Cita agendada correctamente (Simulado).');
    }

    public function adminCitasPrioridadEdit($id)
    {
        $state = $this->getDemoState('admin');
        $cData = collect($state['recentAppointments'])->firstWhere('id', $id);
        if (!$cData) abort(404);
        $cita = $this->mapCita($cData, $state['usuarios']);
        return view('demo.admin.citas-prioridad', [
            'cita' => $cita,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'dashboard']));
    }

    public function adminCitasPrioridadUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['recentAppointments'] as &$c) {
            if ($c['id'] == $id) {
                $c['prioridad_nivel'] = $request->input('prioridad_nivel', 'MEDIA');
                $c['prioridad_red_flag'] = (bool) $request->input('prioridad_red_flag', false);
                $c['prioridad_comentario'] = $request->input('prioridad_comentario', '');
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.dashboard')->with('success', 'Prioridad de cita ajustada correctamente (Simulado).');
    }

    // Pagos
    public function adminPagosShow($id)
    {
        $state = $this->getDemoState('admin');
        $payment = collect($state['payments'])->firstWhere('id', $id);
        if (!$payment) abort(404);
        return view('demo.admin.pagos-show', [
            'pago' => (object) $payment,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'pagos']));
    }

    public function adminPagosAprobar($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['payments'] as &$p) {
            if ($p['id'] == $id) {
                $p['status'] = 'Pagado';
                $p['status_tone'] = 'success';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.pagos.index')->with('success', 'Pago aprobado correctamente (Simulado).');
    }

    public function adminPagosRechazar($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['payments'] as &$p) {
            if ($p['id'] == $id) {
                $p['status'] = 'Rechazado';
                $p['status_tone'] = 'danger';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.pagos.index')->with('success', 'Pago rechazado correctamente (Simulado).');
    }

    public function adminPagosAnular($id)
    {
        $state = $this->getDemoState('admin');
        foreach ($state['payments'] as &$p) {
            if ($p['id'] == $id) {
                $p['status'] = 'Anulado';
                $p['status_tone'] = 'neutral';
                break;
            }
        }
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.pagos.index')->with('success', 'Pago anulado correctamente (Simulado).');
    }

    public function adminPerfilUpdate(Request $request)
    {
        $state = $this->getDemoState('admin');
        $state['adminProfile']['name'] = $request->input('name');
        $state['adminProfile']['email'] = $request->input('email');
        $state['adminProfile']['phone'] = $request->input('telefono');
        $state['adminProfile']['position'] = $request->input('cargo');
        $state['adminProfile']['clinic_address'] = $request->input('direccion');
        $state['adminProfile']['summary'] = $request->input('biografia');
        $this->setDemoState('admin', $state);
        return redirect()->route('demo.admin.perfil.edit')->with('success', 'Perfil administrativo actualizado correctamente (Simulado).');
    }

    public function adminNotificacionesContactoShow($id)
    {
        $state = $this->getDemoState('admin');
        $idInt = is_numeric($id) ? (int) $id : 0;
        $msg = collect($state['contactMessages'] ?? [])->firstWhere('id', $idInt);
        if (!$msg) abort(404);
        return view('demo.admin.notificaciones-contacto-show', [
            'mensaje' => (object) $msg,
        ] + $this->baseData('admin', ['activeSidebarKey' => 'notificaciones-contacto']));
    }

    // ==========================================
    // PACIENTE Action Handlers
    // ==========================================
    public function pacienteAgendarCitaStore(Request $request)
    {
        $state = $this->getDemoState('paciente');
        $id = count($state['citas']) + 1;
        $state['citas'][] = [
            'id' => $id,
            'specialty' => $request->input('especialidad', 'Pediatria'),
            'doctor' => $request->input('doctor', 'Dra. Sofia Cardenas'),
            'date' => $request->input('fecha', '2026-07-15'),
            'time' => $request->input('hora', '10:00'),
            'status' => 'Pendiente',
            'status_tone' => 'warning',
            'priority_label' => 'Media',
            'priority_badge' => 'bg-amber-100 text-amber-700',
        ];
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.citas')->with('success', 'Cita agendada correctamente (Simulado).');
    }

    public function pacienteCitasCancelar($id)
    {
        $state = $this->getDemoState('paciente');
        foreach ($state['citas'] as &$c) {
            if ($c['id'] == $id) {
                $c['status'] = 'Cancelada';
                $c['status_tone'] = 'danger';
                break;
            }
        }
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.citas')->with('success', 'Cita cancelada correctamente (Simulado).');
    }

    public function pacienteCitasEdit($id)
    {
        $state = $this->getDemoState('paciente');
        $cita = collect($state['citas'])->firstWhere('id', $id);
        if (!$cita) abort(404);
        return view('demo.paciente.editar-cita', [
            'cita' => (object) $cita,
        ] + $this->baseData('paciente', ['activeSidebarKey' => 'citas']));
    }

    public function pacienteCitasUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('paciente');
        foreach ($state['citas'] as &$c) {
            if ($c['id'] == $id) {
                $c['date'] = $request->input('fecha');
                $c['time'] = $request->input('hora');
                break;
            }
        }
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.citas')->with('success', 'Cita modificada correctamente (Simulado).');
    }

    public function pacientePagosSubmit(Request $request, $id)
    {
        $state = $this->getDemoState('paciente');
        foreach ($state['payments'] as &$p) {
            if ($p['id'] == $id) {
                $p['status'] = 'En verificacion';
                $p['status_tone'] = 'info';
                $p['method'] = $request->input('metodo', 'Transferencia');
                break;
            }
        }
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.pagos.index')->with('success', 'Comprobante de pago enviado correctamente (Simulado).');
    }

    public function pacientePerfilUpdate(Request $request)
    {
        $state = $this->getDemoState('paciente');
        $state['patientProfile']['name'] = $request->input('name');
        $state['patientProfile']['email'] = $request->input('email');
        $state['patientProfile']['phone'] = $request->input('telefono');
        $state['patientProfile']['blood_type'] = $request->input('grupo_sanguineo');
        $state['patientProfile']['allergies'] = $request->input('alergias');
        $state['patientProfile']['emergency_contact'] = $request->input('contacto_emergencia');
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.perfil.edit')->with('success', 'Perfil actualizado correctamente (Simulado).');
    }

    public function pacienteDependientesIndex()
    {
        $state = $this->getDemoState('paciente');
        return view('demo.paciente.dependientes', [
            'dependientes' => collect($state['dependientes'] ?? []),
        ] + $this->baseData('paciente', ['activeSidebarKey' => 'dependientes']));
    }

    public function pacienteDependientesCreate()
    {
        return view('demo.paciente.dependientes-crear', $this->baseData('paciente', ['activeSidebarKey' => 'dependientes']));
    }

    public function pacienteDependientesStore(Request $request)
    {
        $state = $this->getDemoState('paciente');
        $id = count($state['dependientes']) + 1;
        $state['dependientes'][] = [
            'id' => $id,
            'name' => $request->input('nombre'),
            'dni' => $request->input('dni'),
            'birth_date' => $request->input('fecha_nacimiento'),
            'relationship' => $request->input('parentesco', 'Hijo/a'),
            'status' => 'active',
        ];
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.dependientes.index')->with('success', 'Familiar registrado correctamente (Simulado).');
    }

    public function pacienteDependientesEdit($id)
    {
        $state = $this->getDemoState('paciente');
        $dep = collect($state['dependientes'])->firstWhere('id', $id);
        if (!$dep) abort(404);
        return view('demo.paciente.dependientes-editar', [
            'dependiente' => (object) $dep,
        ] + $this->baseData('paciente', ['activeSidebarKey' => 'dependientes']));
    }

    public function pacienteDependientesUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('paciente');
        foreach ($state['dependientes'] as &$d) {
            if ($d['id'] == $id) {
                $d['name'] = $request->input('nombre');
                $d['dni'] = $request->input('dni');
                $d['birth_date'] = $request->input('fecha_nacimiento');
                $d['relationship'] = $request->input('parentesco');
                break;
            }
        }
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.dependientes.index')->with('success', 'Familiar actualizado correctamente (Simulado).');
    }

    public function pacienteDependientesDestroy($id)
    {
        $state = $this->getDemoState('paciente');
        $state['dependientes'] = array_values(array_filter($state['dependientes'], fn($d) => $d['id'] != $id));
        $this->setDemoState('paciente', $state);
        return redirect()->route('demo.paciente.dependientes.index')->with('success', 'Familiar eliminado correctamente (Simulado).');
    }

    public function pacienteHistorialShow($id)
    {
        return $this->pacienteView('demo.paciente.historial-show', 'historial');
    }

    // ==========================================
    // DOCTOR Action Handlers
    // ==========================================
    public function doctorCitasAceptar($id)
    {
        $state = $this->getDemoState('doctor');
        foreach ($state['citasHoy'] as &$c) {
            if ($c['id'] == $id) {
                $c['status'] = 'Confirmada';
                $c['status_tone'] = 'success';
                break;
            }
        }
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.citas')->with('success', 'Cita aceptada (Simulado).');
    }

    public function doctorCitasRechazar($id)
    {
        $state = $this->getDemoState('doctor');
        foreach ($state['citasHoy'] as &$c) {
            if ($c['id'] == $id) {
                $c['status'] = 'Cancelada';
                $c['status_tone'] = 'danger';
                break;
            }
        }
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.citas')->with('success', 'Cita rechazada (Simulado).');
    }

    public function doctorCitasRealizar($id)
    {
        $state = $this->getDemoState('doctor');
        foreach ($state['citasHoy'] as &$c) {
            if ($c['id'] == $id) {
                $c['status'] = 'Realizada';
                $c['status_tone'] = 'success';
                break;
            }
        }
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.citas')->with('success', 'Cita realizada correctamente (Simulado).');
    }

    public function doctorCitasPrioridadEdit($id)
    {
        return $this->doctorView('demo.doctor.citas-prioridad', 'citas');
    }

    public function doctorCitasPrioridadUpdate(Request $request, $id)
    {
        session()->flash('success', 'Prioridad de cita actualizada por doctor (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorRecetasIndex()
    {
        return $this->doctorView('demo.doctor.historial-recetas', 'historial-recetas');
    }

    public function doctorRecetasCreate($cita)
    {
        return $this->doctorView('demo.doctor.recetas-crear', 'historial-recetas');
    }

    public function doctorRecetasStore(Request $request)
    {
        session()->flash('success', 'Receta médica creada con éxito (Simulado).');
        return redirect()->route('demo.doctor.recetas.index');
    }

    public function doctorRecetasEdit($cita)
    {
        return $this->doctorView('demo.doctor.recetas-editar', 'historial-recetas');
    }

    public function doctorRecetasUpdate(Request $request)
    {
        session()->flash('success', 'Receta médica actualizada con éxito (Simulado).');
        return redirect()->route('demo.doctor.recetas.index');
    }

    public function doctorCertificadosCreate($cita)
    {
        return $this->doctorView('demo.doctor.certificados-crear', 'citas');
    }

    public function doctorCertificadosStore(Request $request)
    {
        session()->flash('success', 'Certificado médico emitido con éxito (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorPedidosLaboratorioIndex()
    {
        return $this->doctorView('demo.doctor.pedidos-laboratorio-index', 'citas');
    }

    public function doctorPedidosLaboratorioCreate($cita)
    {
        return $this->doctorView('demo.doctor.pedidos-laboratorio-crear', 'citas');
    }

    public function doctorPedidosLaboratorioStore(Request $request)
    {
        session()->flash('success', 'Pedido de laboratorio solicitado (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorPedidosLaboratorioEdit($cita)
    {
        return $this->doctorView('demo.doctor.pedidos-laboratorio-editar', 'citas');
    }

    public function doctorPedidosLaboratorioUpdate(Request $request)
    {
        session()->flash('success', 'Pedido de laboratorio actualizado (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorCitasSoap($cita)
    {
        return $this->doctorView('demo.doctor.soap', 'citas');
    }

    public function doctorCitasSoapStore(Request $request, $cita)
    {
        session()->flash('success', 'Nota SOAP guardada (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorCitasSoapFirmar(Request $request, $cita)
    {
        session()->flash('success', 'Nota SOAP firmada electrónicamente (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorCitasSoapEnmienda(Request $request, $cita)
    {
        session()->flash('success', 'Enmienda clínica registrada (Simulado).');
        return redirect()->route('demo.doctor.citas');
    }

    public function doctorPacientesHistorial($id)
    {
        return $this->doctorView('demo.doctor.paciente-historial', 'pacientes');
    }

    public function doctorPacientesHistorialUpdate(Request $request, $id)
    {
        session()->flash('success', 'Ficha clínica del paciente actualizada (Simulado).');
        return redirect()->route('demo.doctor.pacientes.index');
    }

    public function doctorHorarioStore(Request $request)
    {
        $state = $this->getDemoState('doctor');
        $id = count($state['scheduleBlocks']) + 1;
        $state['scheduleBlocks'][] = [
            'id' => $id,
            'day' => $request->input('day', 'Lunes'),
            'range' => $request->input('range', '08:00 - 17:00'),
            'slots' => '14 cupos',
            'state' => 'Operativo',
        ];
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.horario.index')->with('success', 'Bloque de horario guardado (Simulado).');
    }

    public function doctorHorarioEdit($id)
    {
        $state = $this->getDemoState('doctor');
        $horario = collect($state['scheduleBlocks'])->firstWhere('id', $id);
        if (!$horario) abort(404);
        return view('demo.doctor.horario-editar', [
            'horario' => (object) $horario,
        ] + $this->baseData('doctor', ['activeSidebarKey' => 'mi-horario']));
    }

    public function doctorHorarioUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('doctor');
        foreach ($state['scheduleBlocks'] as &$h) {
            if ($h['id'] == $id) {
                $h['range'] = $request->input('range');
                break;
            }
        }
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.horario.index')->with('success', 'Bloque de horario actualizado (Simulado).');
    }

    public function doctorHorarioDestroy($id)
    {
        $state = $this->getDemoState('doctor');
        $state['scheduleBlocks'] = array_values(array_filter($state['scheduleBlocks'], fn($h) => $h['id'] != $id));
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.horario.index')->with('success', 'Horario eliminado (Simulado).');
    }

    public function doctorPerfilUpdate(Request $request)
    {
        $state = $this->getDemoState('doctor');
        $state['doctorProfile']['name'] = $request->input('name');
        $state['doctorProfile']['email'] = $request->input('email');
        $state['doctorProfile']['phone'] = $request->input('telefono');
        $state['doctorProfile']['summary'] = $request->input('biografia');
        $this->setDemoState('doctor', $state);
        return redirect()->route('demo.doctor.perfil.edit')->with('success', 'Perfil médico actualizado (Simulado).');
    }

    // ==========================================
    // LABORATORIO Action Handlers
    // ==========================================
    public function laboratorioHorarioStore(Request $request)
    {
        $state = $this->getDemoState('laboratorio');
        $id = count($state['scheduleBlocks']) + 1;
        $state['scheduleBlocks'][] = [
            'id' => $id,
            'day' => $request->input('day', 'Lunes'),
            'range' => $request->input('range', '07:00 - 16:00'),
            'state' => 'Recepcion y procesamiento',
            'slots' => '18 turnos',
        ];
        $this->setDemoState('laboratorio', $state);
        return redirect()->route('demo.laboratorio.horario.index')->with('success', 'Horario de laboratorio guardado (Simulado).');
    }

    public function laboratorioHorarioEdit($id)
    {
        $state = $this->getDemoState('laboratorio');
        $horario = collect($state['scheduleBlocks'])->firstWhere('id', $id);
        if (!$horario) abort(404);
        return view('demo.laboratorio.horario-editar', [
            'horario' => (object) $horario,
        ] + $this->baseData('laboratorio', ['activeSidebarKey' => 'horarios']));
    }

    public function laboratorioHorarioUpdate(Request $request, $id)
    {
        $state = $this->getDemoState('laboratorio');
        foreach ($state['scheduleBlocks'] as &$h) {
            if ($h['id'] == $id) {
                $h['range'] = $request->input('range');
                break;
            }
        }
        $this->setDemoState('laboratorio', $state);
        return redirect()->route('demo.laboratorio.horario.index')->with('success', 'Horario de laboratorio actualizado (Simulado).');
    }

    public function laboratorioHorarioDestroy($id)
    {
        $state = $this->getDemoState('laboratorio');
        $state['scheduleBlocks'] = array_values(array_filter($state['scheduleBlocks'], fn($h) => $h['id'] != $id));
        $this->setDemoState('laboratorio', $state);
        return redirect()->route('demo.laboratorio.horario.index')->with('success', 'Horario de laboratorio eliminado (Simulado).');
    }

    public function laboratorioOrdenesIndex()
    {
        return $this->laboratorioView('demo.laboratorio.ordenes-index', 'citas-resultados');
    }

    public function laboratorioOrdenesMuestra($orden)
    {
        $state = $this->getDemoState('laboratorio');
        foreach ($state['ordenes'] as &$o) {
            if ($o['id'] == $orden) {
                $o['status'] = 'En proceso';
                $o['status_tone'] = 'info';
                break;
            }
        }
        $this->setDemoState('laboratorio', $state);
        return redirect()->route('demo.laboratorio.citas-resultados')->with('success', 'Muestra tomada correctamente (Simulado).');
    }

    public function laboratorioOrdenesResultado($orden)
    {
        $state = $this->getDemoState('laboratorio');
        foreach ($state['ordenes'] as &$o) {
            if ($o['id'] == $orden) {
                $o['status'] = 'Completado';
                $o['status_tone'] = 'success';
                break;
            }
        }
        $this->setDemoState('laboratorio', $state);
        return redirect()->route('demo.laboratorio.citas-resultados')->with('success', 'Resultados de laboratorio publicados con éxito (Simulado).');
    }

    public function laboratorioPedidosMvpIndex()
    {
        return $this->laboratorioView('demo.laboratorio.pedidos-mvp', 'citas-resultados');
    }

    public function laboratorioPedidosMuestra($id)
    {
        return $this->laboratorioOrdenesMuestra($id);
    }

    public function laboratorioPedidosResultado($id)
    {
        return $this->laboratorioOrdenesResultado($id);
    }

    private function superadminView(string $view, string $active)
    {
        $period = request()->get('period', '30d');
        if (!in_array($period, ['all', '7d', '30d', 'month', 'year'])) {
            $period = '30d';
        }

        $now = now();
        $start = match ($period) {
            '7d' => now()->subDays(7)->toDateString(),
            '30d' => now()->subDays(30)->toDateString(),
            'month' => now()->startOfMonth()->toDateString(),
            'year' => now()->startOfYear()->toDateString(),
            'all' => now()->subDays(365)->toDateString(),
        };

        $labels = [];
        $citasData = [];
        $usuariosData = [];

        if ($period === '7d') {
            for ($i = 6; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);
                $labels[] = str_replace('.', '', $date->locale('es')->translatedFormat('d M'));
            }
            $citasData = [30, 45, 38, 42, 50, 35, 40];
            $usuariosData = [5, 10, 8, 12, 15, 7, 9];
        } elseif ($period === '30d') {
            for ($i = 29; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);
                $labels[] = str_replace('.', '', $date->locale('es')->translatedFormat('d M'));
                $citasData[] = 25 + (($i * 7 + 13) % 25);
                $usuariosData[] = 3 + (($i * 3 + 7) % 12);
            }
        } elseif ($period === 'month') {
            $daysInMonth = $now->daysInMonth;
            $startOfMonth = $now->copy()->startOfMonth();
            for ($i = 0; $i < $daysInMonth; $i++) {
                $date = $startOfMonth->copy()->addDays($i);
                if ($date->gt($now)) {
                    break;
                }
                $labels[] = str_replace('.', '', $date->locale('es')->translatedFormat('d M'));
                $citasData[] = 20 + (($i * 11 + 5) % 30);
                $usuariosData[] = 2 + (($i * 5 + 3) % 10);
            }
        } elseif ($period === 'year') {
            for ($i = 11; $i >= 0; $i--) {
                $date = $now->copy()->subMonths($i);
                $labels[] = ucfirst(str_replace('.', '', $date->locale('es')->translatedFormat('M')));
                $citasData[] = 600 + (($i * 150 + 200) % 500);
                $usuariosData[] = 120 + (($i * 40 + 50) % 100);
            }
        } elseif ($period === 'all') {
            for ($i = 4; $i >= 0; $i--) {
                $date = $now->copy()->subYears($i);
                $labels[] = $date->format('Y');
                $citasData[] = 5000 + (($i * 2000 + 1000) % 8000);
                $usuariosData[] = 1000 + (($i * 500 + 200) % 2000);
            }
        }

        $dashboard = [
            'filters' => [
                'period' => $period,
                'start' => $start,
                'end' => now()->toDateString(),
                'grouping' => $period === 'year' || $period === 'all' ? 'month' : 'day',
            ],
            'charts' => [
                'usuarios_roles' => [
                    'type' => 'donut',
                    'labels' => ['Superadmin', 'Administradores', 'Doctores', 'Pacientes', 'Laboratorios'],
                    'series' => [2, 12, 45, 1850, 12],
                    'colors' => ['#6366f1', '#f59e0b', '#0ea5e9', '#10b981', '#ec4899'],
                ],
                'citas_estado' => [
                    'type' => 'donut',
                    'labels' => ['Pendiente', 'Confirmada', 'Cancelada', 'Realizada', 'No se presentó'],
                    'series' => [142, 450, 80, 1200, 50],
                    'colors' => ['#f59e0b', '#0ea5e9', '#ef4444', '#10b981', '#64748b'],
                ],
                'citas_timeline' => [
                    'type' => 'area',
                    'labels' => $labels,
                    'series' => [
                        [
                            'name' => 'Citas programadas',
                            'data' => $citasData
                        ]
                    ],
                    'colors' => ['#0d9488'],
                ],
                'citas_doctor' => [
                    'type' => 'bar',
                    'stacked' => true,
                    'horizontal' => true,
                    'labels' => ['Dra. Maria Gonzalez', 'Dr. Carlos Ruiz', 'Dra. Sofia Herrera', 'Dr. Daniel Mora', 'Dra. Ana Torres', 'Sin asignar'],
                    'series' => [
                        [
                            'name' => 'Pendiente',
                            'data' => [12, 10, 8, 5, 3, 0]
                        ],
                        [
                            'name' => 'Confirmada',
                            'data' => [30, 25, 20, 15, 10, 0]
                        ],
                        [
                            'name' => 'Realizada',
                            'data' => [120, 90, 80, 60, 40, 0]
                        ],
                        [
                            'name' => 'Cancelada',
                            'data' => [10, 8, 5, 4, 2, 0]
                        ],
                        [
                            'name' => 'No se presentó',
                            'data' => [5, 3, 2, 1, 0, 0]
                        ]
                    ],
                    'colors' => ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444', '#64748b'],
                ],
                'documentos_tipo' => [
                    'type' => 'donut',
                    'labels' => ['Recetas', 'Certificados', 'Pedidos lab'],
                    'series' => [843, 312, 521],
                    'colors' => ['#0284c7', '#ec4899', '#f59e0b'],
                ],
                'usuarios_timeline' => [
                    'type' => 'area',
                    'labels' => $labels,
                    'series' => [
                        [
                            'name' => 'Usuarios nuevos',
                            'data' => $usuariosData
                        ]
                    ],
                    'colors' => ['#3b82f6'],
                ],
            ]
        ];

        $superadminData = $this->superadminData();
        // Always compute pending count from fixed initial data, never from session.
        $pendingCount = collect($superadminData['solicitudes'])
            ->where('status', 'pending')
            ->count();
        $superadminData['pendingPersonalizacion'] = $pendingCount;

        return view($view, array_merge(
            $this->baseData('superadmin', [
                'activeSidebarKey' => $active,
                'pendingPersonalizacion' => $pendingCount,
            ]),
            $superadminData,
            ['dashboard' => $dashboard]
        ));
    }


    private function getDemoState(string $role)
    {
        $currentRole = session()->get('demo_current_role');
        if ($currentRole && $currentRole !== $role) {
            session()->forget([
                'demo_state_admin',
                'demo_state_doctor',
                'demo_state_paciente',
                'demo_state_laboratorio'
            ]);
        }
        session()->put('demo_current_role', $role);

        $sessionKey = "demo_state_{$role}";
        if (request()->query('init_demo') === '1' || !session()->has($sessionKey)) {
            $defaults = match ($role) {
                'admin' => $this->adminDataDefaults(),
                'doctor' => $this->doctorDataDefaults(),
                'paciente' => $this->pacienteDataDefaults(),
                'laboratorio' => $this->laboratorioDataDefaults(),
                default => [],
            };
            session()->put($sessionKey, $defaults);
        }
        return session()->get($sessionKey);
    }

    private function setDemoState(string $role, array $state)
    {
        session()->put("demo_state_{$role}", $state);
    }

    private function mapUser(array $uData): User
    {
        $user = new User($uData);
        $user->id = $uData['id'] ?? 1;
        $user->exists = true;
        
        $roleName = strtolower($uData['role'] ?? 'paciente');
        $role = new Role(['name' => $roleName]);
        $user->setRelation('roles', collect([$role]));
        
        $esps = collect();
        if (!empty($uData['specialty']) && $uData['specialty'] !== 'Sin especialidad') {
            $esps->push(new Especialidad(['nombre' => $uData['specialty']]));
        }
        $user->setRelation('especialidades', $esps);

        if (!empty($uData['suspended_until'])) {
            $user->suspended_until = Carbon::parse($uData['suspended_until']);
        }
        if (!empty($uData['last_login_at'])) {
            $user->last_login_at = Carbon::parse($uData['last_login_at']);
        }
        
        return $user;
    }

    private function mapCita(array $cData, array $usersData): Cita
    {
        $cita = new Cita($cData);
        $cita->id = $cData['id'] ?? 1;
        $cita->exists = true;
        
        $cita->fecha = Carbon::parse($cData['fecha'] ?? now());
        $cita->hora = $cData['hora'] ?? '08:00';
        $cita->estado = $cData['status'] ?? 'pendiente';
        $cita->prioridad_nivel = $cData['prioridad_nivel'] ?? 'BAJA';
        $cita->prioridad_red_flag = (bool) ($cData['prioridad_red_flag'] ?? false);
        $cita->prioridad_comentario = $cData['prioridad_comentario'] ?? '';
        $cita->motivo_consulta = $cData['motivo'] ?? 'Consulta';

        $pName = $cData['patient'] ?? 'Paciente';
        $pData = collect($usersData)->firstWhere('name', $pName);
        if ($pData) {
            $cita->setRelation('paciente', $this->mapUser($pData));
        } else {
            $cita->setRelation('paciente', new User(['name' => $pName, 'dni' => '1723456789']));
        }

        $dName = $cData['doctor'] ?? 'Doctor';
        $dData = collect($usersData)->firstWhere('name', $dName);
        if ($dData) {
            $cita->setRelation('doctor', $this->mapUser($dData));
        } else {
            $cita->setRelation('doctor', new User(['name' => $dName]));
        }

        return $cita;
    }

    private function adminView(string $view, string $active)
    {
        $state = $this->getDemoState('admin');
        
        $dashboard = [
            'filters' => [
                'period' => '30d',
                'start' => now()->subDays(30)->toDateString(),
                'end' => now()->toDateString(),
                'grouping' => 'day',
                'label' => 'Últimos 30 días',
            ],
            'charts' => [
                'citas_estado' => [
                    'type' => 'donut',
                    'labels' => ['Pendiente', 'Confirmada', 'Cancelada', 'Realizada', 'No se presentó'],
                    'series' => [14, 45, 8, 120, 5],
                    'colors' => ['#f59e0b', '#0ea5e9', '#ef4444', '#10b981', '#64748b'],
                ],
                'citas_doctor' => [
                    'type' => 'bar',
                    'stacked' => true,
                    'horizontal' => true,
                    'labels' => ['Dra. Sofia Cardenas', 'Dr. Andres Molina', 'Dr. Daniel Mora', 'Sin asignar'],
                    'series' => [
                        [
                            'name' => 'Pendiente',
                            'data' => [2, 1, 3, 0]
                        ],
                        [
                            'name' => 'Confirmada',
                            'data' => [12, 8, 15, 0]
                        ],
                        [
                            'name' => 'Realizada',
                            'data' => [45, 30, 22, 0]
                        ],
                        [
                            'name' => 'Cancelada',
                            'data' => [3, 2, 1, 0]
                        ],
                        [
                            'name' => 'No se presentó',
                            'data' => [1, 0, 2, 0]
                        ]
                    ],
                    'colors' => ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444', '#64748b'],
                ],
                'citas_por_dia_estado' => [
                    'type' => 'bar',
                    'stacked' => true,
                    'labels' => ['01 Jul', '02 Jul', '03 Jul', '04 Jul', '05 Jul', '06 Jul', '07 Jul'],
                    'series' => [
                        [
                            'name' => 'Pendiente',
                            'data' => [2, 1, 3, 1, 2, 0, 1]
                        ],
                        [
                            'name' => 'Confirmada',
                            'data' => [5, 6, 8, 4, 5, 3, 4]
                        ],
                        [
                            'name' => 'Realizada',
                            'data' => [15, 18, 12, 14, 16, 11, 13]
                        ],
                        [
                            'name' => 'Cancelada',
                            'data' => [1, 0, 2, 0, 1, 1, 0]
                        ],
                        [
                            'name' => 'No se presentó',
                            'data' => [0, 1, 0, 0, 1, 0, 0]
                        ]
                    ],
                    'colors' => ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444', '#64748b'],
                ],
                'pacientes_nuevos_atendidos' => [
                    'type' => 'bar',
                    'labels' => ['01 Jul', '02 Jul', '03 Jul', '04 Jul', '05 Jul', '06 Jul', '07 Jul'],
                    'series' => [
                        [
                            'name' => 'Nuevos',
                            'data' => [8, 12, 6, 9, 11, 7, 10]
                        ],
                        [
                            'name' => 'Atendidos',
                            'data' => [15, 18, 12, 14, 16, 11, 13]
                        ]
                    ],
                    'colors' => ['#0d9488', '#0284c7'],
                ],
            ]
        ];

        return view($view, array_merge(
            $this->baseData('admin', ['activeSidebarKey' => $active]),
            $state,
            ['dashboard' => $dashboard]
        ));
    }

    private function pacienteView(string $view, string $active)
    {
        $state = $this->getDemoState('paciente');
        return view($view, array_merge(
            $this->baseData('paciente', ['activeSidebarKey' => $active]),
            $state
        ));
    }

    private function doctorView(string $view, string $active)
    {
        $state = $this->getDemoState('doctor');
        
        $dashboard = [
            'filters' => [
                'period' => '30d',
                'start' => now()->subDays(30)->toDateString(),
                'end' => now()->toDateString(),
                'grouping' => 'day',
            ],
            'charts' => [
                'appointments_status' => [
                    'type' => 'donut',
                    'labels' => ['Pendiente', 'Confirmada', 'Cancelada', 'Realizada', 'No se presentó'],
                    'series' => [3, 10, 2, 45, 1],
                    'colors' => ['#f59e0b', '#0ea5e9', '#ef4444', '#10b981', '#64748b'],
                ],
                'patients_timeline' => [
                    'type' => 'area',
                    'labels' => ['01 Jul', '02 Jul', '03 Jul', '04 Jul', '05 Jul', '06 Jul', '07 Jul'],
                    'series' => [
                        [
                            'name' => 'Pacientes atendidos',
                            'data' => [5, 8, 4, 6, 7, 5, 6]
                        ]
                    ],
                    'colors' => ['#0d9488'],
                ],
                'appointments_status_timeline' => [
                    'type' => 'bar',
                    'stacked' => true,
                    'labels' => ['01 Jul', '02 Jul', '03 Jul', '04 Jul', '05 Jul', '06 Jul', '07 Jul'],
                    'series' => [
                        [
                            'name' => 'Pendiente',
                            'data' => [0, 1, 0, 1, 0, 0, 1]
                        ],
                        [
                            'name' => 'Confirmada',
                            'data' => [1, 2, 1, 1, 2, 1, 1]
                        ],
                        [
                            'name' => 'Realizada',
                            'data' => [4, 5, 3, 4, 5, 4, 4]
                        ],
                        [
                            'name' => 'Cancelada',
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ],
                        [
                            'name' => 'No se presentó',
                            'data' => [0, 0, 0, 0, 0, 0, 0]
                        ]
                    ],
                    'colors' => ['#f59e0b', '#0ea5e9', '#10b981', '#ef4444', '#64748b'],
                ],
                'documents_type' => [
                    'type' => 'bar',
                    'labels' => ['Recetas', 'Certificados', 'Pedidos lab'],
                    'series' => [
                        [
                            'name' => 'Documentos',
                            'data' => [15, 5, 8]
                        ]
                    ],
                    'colors' => ['#0284c7'],
                ],
                'controls_status' => [
                    'type' => 'donut',
                    'labels' => ['Controles programados', 'Controles realizados'],
                    'series' => [8, 12],
                    'colors' => ['#6366f1', '#10b981'],
                ],
            ]
        ];

        return view($view, array_merge(
            $this->baseData('doctor', ['activeSidebarKey' => $active]),
            $state,
            ['dashboard' => $dashboard]
        ));
    }

    private function laboratorioView(string $view, string $active)
    {
        $state = $this->getDemoState('laboratorio');
        return view($view, array_merge(
            $this->baseData('laboratorio', ['activeSidebarKey' => $active]),
            $state
        ));
    }

    private function baseData(string $role, array $overrides = []): array
    {
        $profiles = [
            'landing' => [
                'name' => 'Explora la demo',
                'email' => 'demo.publica@clinica.test',
                'roleLabel' => 'Demo publica',
                'avatarInitials' => 'DP',
            ],
            'superadmin' => [
                'name' => 'Superadmin Demo',
                'email' => 'superadmin.demo@clinica.test',
                'roleLabel' => 'Panel Superadmin',
                'avatarInitials' => 'SD',
            ],
            'admin' => [
                'name' => 'Administrador Demo',
                'email' => 'admin.demo@clinica.test',
                'roleLabel' => 'Panel Admin',
                'avatarInitials' => 'AD',
            ],
            'paciente' => [
                'name' => 'Paciente Demo',
                'email' => 'paciente.demo@clinica.test',
                'roleLabel' => 'Portal Paciente',
                'avatarInitials' => 'PD',
            ],
            'doctor' => [
                'name' => 'Doctor Demo',
                'email' => 'doctor.demo@clinica.test',
                'roleLabel' => 'Panel Medico',
                'avatarInitials' => 'DD',
            ],
            'laboratorio' => [
                'name' => 'Laboratorio Demo',
                'email' => 'laboratorio.demo@clinica.test',
                'roleLabel' => 'Laboratorio',
                'avatarInitials' => 'LD',
            ],
        ];

        $defaults = [
            'clinicName' => 'Nombre de la clinica',
            'demoRole' => $role,
            'demoUser' => $profiles[$role],
            'logoutUrl' => route('demo.index', ['notice' => 'logout']),
            'headerTitle' => $profiles[$role]['roleLabel'],
            'headerSubtitle' => 'Datos simulados y acciones bloqueadas.',
            'activeSidebarKey' => 'dashboard',
            'showRoleSwitcher' => $role !== 'landing',
        ];

        return array_merge($defaults, $overrides);
    }

    private function roleCards(): array
    {
        return [
            [
                'name' => 'Superadmin',
                'description' => 'Gestiona administradores, solicitudes y mantenimiento.',
                'icon' => 'ri-shield-star-line',
                'icon_classes' => 'bg-teal-100 text-teal-700',
                'hover_classes' => 'group-hover:text-teal-700',
                'route' => route('demo.superadmin.dashboard'),
            ],
            [
                'name' => 'Administrador',
                'description' => 'Gestiona usuarios, historial, pagos y agenda.',
                'icon' => 'ri-hospital-line',
                'icon_classes' => 'bg-sky-100 text-sky-700',
                'hover_classes' => 'group-hover:text-sky-700',
                'route' => route('demo.admin.dashboard'),
            ],
            [
                'name' => 'Paciente',
                'description' => 'Agenda citas, revisa resultados y consulta cobros.',
                'icon' => 'ri-user-heart-line',
                'icon_classes' => 'bg-blue-100 text-blue-700',
                'hover_classes' => 'group-hover:text-blue-700',
                'route' => route('demo.paciente.dashboard'),
            ],
            [
                'name' => 'Doctor',
                'description' => 'Revisa agenda, pacientes, recetas y horario.',
                'icon' => 'ri-stethoscope-line',
                'icon_classes' => 'bg-emerald-100 text-emerald-700',
                'hover_classes' => 'group-hover:text-emerald-700',
                'route' => route('demo.doctor.dashboard'),
            ],
            [
                'name' => 'Laboratorio',
                'description' => 'Gestiona muestras, resultados y horario.',
                'icon' => 'ri-flask-line',
                'icon_classes' => 'bg-amber-100 text-amber-700',
                'hover_classes' => 'group-hover:text-amber-700',
                'route' => route('demo.laboratorio.dashboard'),
            ],
        ];
    }

    private function superadminData(): array
    {
        return [
            'pendingPersonalizacion' => 2,
            'kpis' => [
                'total_usuarios' => 2500,
                'pacientes' => 1850,
                'doctores' => 45,
                'total_admins' => 12,
                'laboratorios' => 12,
                'citas_totales' => 8432,
                'citas_hoy' => 142,
                'pendientes_hoy' => 38,
            ],
            'activity' => [
                ['label' => 'Lun', 'citas' => 84, 'usuarios' => 7, 'height' => 54],
                ['label' => 'Mar', 'citas' => 96, 'usuarios' => 9, 'height' => 62],
                ['label' => 'Mie', 'citas' => 104, 'usuarios' => 11, 'height' => 74],
                ['label' => 'Jue', 'citas' => 112, 'usuarios' => 10, 'height' => 82],
                ['label' => 'Vie', 'citas' => 120, 'usuarios' => 13, 'height' => 92],
                ['label' => 'Sab', 'citas' => 68, 'usuarios' => 5, 'height' => 44],
                ['label' => 'Dom', 'citas' => 40, 'usuarios' => 3, 'height' => 28],
            ],
            'administradores' => [
                [
                    'id' => 2,
                    'name' => 'Josthyn Admin',
                    'email' => 'josthyn.admin@demo.clinica',
                    'telefono' => '0995140927',
                    'status' => 'active',
                    'last_login_at' => now()->subHours(19),
                ],
                [
                    'id' => 7,
                    'name' => 'Alejandro Bautista',
                    'email' => 'alejandro.b@demo.clinica',
                    'telefono' => '',
                    'status' => 'active',
                    'last_login_at' => null,
                ],
                [
                    'id' => 9,
                    'name' => 'Admin Operaciones',
                    'email' => 'operaciones@demo.clinica',
                    'telefono' => '0987654321',
                    'status' => 'inactive',
                    'last_login_at' => now()->subDays(3),
                ]
            ],
            'usuarios' => [
                [
                    'name' => 'Josthyn Arroyo',
                    'email' => 'josthyn.arroyo@demo.clinica',
                    'dni' => '0912345678',
                    'role' => 'superadmin',
                    'status' => 'active',
                ],
                [
                    'name' => 'Admin Principal',
                    'email' => 'admin.principal@demo.clinica',
                    'dni' => '1712345678',
                    'role' => 'administrador',
                    'status' => 'active',
                ],
                [
                    'name' => 'Carlos Perez',
                    'email' => 'carlos.perez@demo.clinica',
                    'dni' => '0922345678',
                    'role' => 'paciente',
                    'status' => 'active',
                ],
                [
                    'name' => 'Dra. Sofia Herrera',
                    'email' => 'sofia.herrera@demo.clinica',
                    'dni' => '1722345678',
                    'role' => 'doctor',
                    'status' => 'active',
                ],
                [
                    'name' => 'Pedro Lab',
                    'email' => 'pedro.lab@demo.clinica',
                    'dni' => '0932345678',
                    'role' => 'laboratorio',
                    'status' => 'active',
                ],
                [
                    'name' => 'Ana Torres',
                    'email' => 'ana.torres@demo.clinica',
                    'dni' => '1732345678',
                    'role' => 'paciente',
                    'status' => 'inactive',
                ],
                [
                    'name' => 'Luis Fernandez',
                    'email' => 'luis.f@demo.clinica',
                    'dni' => '0942345678',
                    'role' => 'laboratorio',
                    'status' => 'blocked',
                ],
            ],
            'solicitudes' => [
                [
                    'id' => 1,
                    'admin' => 'Josthyn Admin',
                    'email' => 'josthyn.admin@demo.clinica',
                    'created_at' => now()->subHours(2),
                    'status' => 'pending',
                    'expiry' => null,
                    'notes' => null,
                ],
                [
                    'id' => 2,
                    'admin' => 'Alejandro Bautista',
                    'email' => 'alejandro.b@demo.clinica',
                    'created_at' => now()->subDays(2),
                    'status' => 'approved',
                    'expiry' => 'Sin vencimiento',
                    'notes' => null,
                ],
                [
                    'id' => 3,
                    'admin' => 'Dra. Maria Gonzalez',
                    'email' => 'maria.gonzalez@demo.clinica',
                    'created_at' => now()->subDays(3),
                    'status' => 'rejected',
                    'expiry' => null,
                    'notes' => 'Falta justificar el módulo extra.',
                ],
                [
                    'id' => 4,
                    'admin' => 'Dr. Carlos Ruiz',
                    'email' => 'carlos.ruiz@demo.clinica',
                    'created_at' => now()->subDays(5),
                    'status' => 'expired',
                    'expiry' => now()->subDays(1)->format('Y-m-d H:i'),
                    'notes' => null,
                ],
                [
                    'id' => 5,
                    'admin' => 'Dr. Daniel Mora',
                    'email' => 'daniel.mora@demo.clinica',
                    'created_at' => now()->subDays(10),
                    'status' => 'revoked',
                    'expiry' => now()->subDays(7)->format('Y-m-d H:i'),
                    'notes' => 'Revocado por vencimiento de contrato.',
                ]
            ],
            'brandingSections' => [
                ['label' => 'Titulo principal', 'value' => 'Clinica demo con agenda, laboratorio y pagos'],
                ['label' => 'Subtitulo', 'value' => 'Explora el sistema sin iniciar sesion y sin tocar datos reales.'],
                ['label' => 'Color principal', 'value' => '#0f766e'],
                ['label' => 'Color de apoyo', 'value' => '#1d4ed8'],
            ],
            'maintenance' => [
                'enabled' => false,
                'window' => 'Domingos 02:00 - 04:00',
                'last_change' => '24/04/2026 18:30',
                'message' => 'Regresamos pronto. Estamos actualizando la demo de la clinica.',
            ],
            'respaldos' => [
                [
                    'id' => 1,
                    'type' => 'manual',
                    'status' => 'verified',
                    'created_at' => '08/08/2026 16:42:00',
                    'formatted_size' => '124.5 MB',
                    'duration_seconds' => 14,
                    'user_name' => 'Josthyn Admin',
                    'sha256' => 'a1b2c3d4e5f678901234567890abcdef1234567890abcdef1234567890abcdef',
                    'last_verified_at' => '08/08/2026 17:00:00',
                ],
                [
                    'id' => 2,
                    'type' => 'daily',
                    'status' => 'completed',
                    'created_at' => '08/08/2026 02:00:00',
                    'formatted_size' => '121.2 MB',
                    'duration_seconds' => 12,
                    'user_name' => 'Sistema (Automático)',
                    'sha256' => 'b2c3d4e5f678901234567890abcdef1234567890abcdef1234567890abcdef1',
                    'last_verified_at' => '08/08/2026 02:15:00',
                ],
                [
                    'id' => 3,
                    'type' => 'daily',
                    'status' => 'completed',
                    'created_at' => '07/08/2026 02:00:00',
                    'formatted_size' => '118.8 MB',
                    'duration_seconds' => 11,
                    'user_name' => 'Sistema (Automático)',
                    'sha256' => 'c3d4e5f678901234567890abcdef1234567890abcdef1234567890abcdef12',
                    'last_verified_at' => '07/08/2026 02:15:00',
                ],
                [
                    'id' => 4,
                    'type' => 'weekly',
                    'status' => 'verified',
                    'created_at' => '03/08/2026 02:00:00',
                    'formatted_size' => '115.0 MB',
                    'duration_seconds' => 10,
                    'user_name' => 'Sistema (Automático)',
                    'sha256' => 'd4e5f678901234567890abcdef1234567890abcdef1234567890abcdef123',
                    'last_verified_at' => '03/08/2026 03:00:00',
                ],
            ],
        ];
    }

    private function adminData(): array
    {
        return $this->getDemoState('admin');
    }

    private function pacienteData(): array
    {
        return $this->getDemoState('paciente');
    }

    private function doctorData(): array
    {
        return $this->getDemoState('doctor');
    }

    private function laboratorioData(): array
    {
        return $this->getDemoState('laboratorio');
    }

    private function adminDataDefaults(): array
    {
        return [
            'kpis' => [
                'total_pacientes' => 1250,
                'total_doctores' => 34,
                'usuarios_activos' => 45,
                'citas_mes' => 340,
                'ingresos_mes' => 8500.00,
            ],
            'recentAppointments' => [
                ['id' => 1, 'time' => '08:00', 'patient' => 'Maria Fernanda Vega', 'doctor' => 'Dra. Sofia Cardenas', 'specialty' => 'Pediatria', 'status' => 'Confirmada', 'status_tone' => 'info', 'fecha' => now()->toDateString(), 'hora' => '08:00', 'prioridad_nivel' => 'MEDIA', 'prioridad_red_flag' => false],
                ['id' => 2, 'time' => '09:00', 'patient' => 'Lucia Vega', 'doctor' => 'Dra. Sofia Cardenas', 'specialty' => 'Pediatria', 'status' => 'Pendiente', 'status_tone' => 'warning', 'fecha' => now()->toDateString(), 'hora' => '09:00', 'prioridad_nivel' => 'ALTA', 'prioridad_red_flag' => true],
                ['id' => 3, 'time' => '10:30', 'patient' => 'Daniel Salazar', 'doctor' => 'Dr. Andres Molina', 'specialty' => 'Medicina general', 'status' => 'Confirmada', 'status_tone' => 'info', 'fecha' => now()->toDateString(), 'hora' => '10:30', 'prioridad_nivel' => 'BAJA', 'prioridad_red_flag' => false],
                ['id' => 4, 'time' => '11:15', 'patient' => 'Elena Silva', 'doctor' => 'Dr. Daniel Mora', 'specialty' => 'Dermatologia', 'status' => 'Realizada', 'status_tone' => 'success', 'fecha' => now()->toDateString(), 'hora' => '11:15', 'prioridad_nivel' => 'BAJA', 'prioridad_red_flag' => false],
            ],
            'recentPayments' => [
                ['reference' => 'TRX-1001', 'patient' => 'Maria Fernanda Vega', 'amount' => '$35.00', 'age' => 'Hace 1 hora'],
                ['reference' => 'TRX-1002', 'patient' => 'Lucia Vega', 'amount' => '$28.00', 'age' => 'Hace 2 horas'],
                ['reference' => 'TRX-1003', 'patient' => 'Daniel Salazar', 'amount' => '$40.00', 'age' => 'Hace 4 horas'],
            ],
            'usuarios' => [
                ['id' => 10, 'name' => 'Maria Fernanda Vega', 'email' => 'maria.fernanda@example.com', 'role' => 'Paciente', 'role_tone' => 'success', 'status' => 'active', 'status_tone' => 'success', 'specialty' => 'Sin especialidad', 'last_login' => 'Hace 10 min', 'registered_at' => '05/04/2026', 'telefono' => '0995140927', 'dni' => '1723456789'],
                ['id' => 11, 'name' => 'Dra. Sofia Cardenas', 'email' => 'sofia.cardenas@example.com', 'role' => 'Doctor', 'role_tone' => 'info', 'status' => 'active', 'status_tone' => 'success', 'specialty' => 'Pediatria', 'last_login' => 'Hace 35 min', 'registered_at' => '14/03/2026', 'telefono' => '0987654321', 'dni' => '1712345672'],
                ['id' => 12, 'name' => 'Dr. Andres Molina', 'email' => 'andres.molina@example.com', 'role' => 'Doctor', 'role_tone' => 'info', 'status' => 'active', 'status_tone' => 'success', 'specialty' => 'Medicina general', 'last_login' => 'Hace 50 min', 'registered_at' => '09/02/2026', 'telefono' => '0964412222', 'dni' => '1712345673'],
                ['id' => 13, 'name' => 'Daniel Salazar', 'email' => 'daniel.salazar@example.com', 'role' => 'Paciente', 'role_tone' => 'success', 'status' => 'active', 'status_tone' => 'success', 'specialty' => 'Sin especialidad', 'last_login' => 'Hace 3 dias', 'registered_at' => '22/01/2026', 'telefono' => '0950001111', 'dni' => '0923456781'],
                ['id' => 14, 'name' => 'Pedro Lab', 'email' => 'pedro.lab@example.com', 'role' => 'Laboratorio', 'role_tone' => 'warning', 'status' => 'active', 'status_tone' => 'success', 'specialty' => 'Bioquimica', 'last_login' => 'Hace 2 horas', 'registered_at' => '01/01/2026', 'telefono' => '0981002333', 'dni' => '1712345674'],
            ],
            'paymentTotals' => [
                'pendiente' => 6,
                'en_verificacion' => 4,
                'rechazado' => 1,
                'pagado' => 28,
                'anulado' => 0,
            ],
            'payments' => [
                ['id' => 'P-001', 'folio' => 'FOL-2026-1001', 'patient' => 'Maria Fernanda Vega', 'appointment' => 'Consulta Pediatria', 'amount' => '$35.00', 'method' => 'TRANSFERENCIA', 'status' => 'Pagado', 'status_tone' => 'success', 'date' => '28/04/2026 08:40'],
                ['id' => 'P-002', 'folio' => 'FOL-2026-1002', 'patient' => 'Lucia Vega', 'appointment' => 'Control Pediatria', 'amount' => '$28.00', 'method' => 'TRANSFERENCIA', 'status' => 'En verificacion', 'status_tone' => 'info', 'date' => '28/04/2026 09:10'],
                ['id' => 'P-003', 'folio' => 'FOL-2026-1003', 'patient' => 'Daniel Salazar', 'appointment' => 'Medicina general', 'amount' => '$40.00', 'method' => 'EFECTIVO', 'status' => 'Pendiente', 'status_tone' => 'warning', 'date' => '27/04/2026 17:20'],
            ],
            'historialEntries' => [
                ['patient' => 'Lucia Vega', 'doctor' => 'Dra. Sofia Cardenas', 'specialty' => 'Pediatria', 'date' => '2026-04-28 08:00', 'summary' => 'Dolor al tragar desde hace tres dias, con congestion y malestar durante la noche.'],
                ['patient' => 'Daniel Salazar', 'doctor' => 'Dr. Andres Molina', 'specialty' => 'Medicina general', 'date' => '2026-04-27 15:30', 'summary' => 'Chequeo de rutina por hipertension arterial, presion estable.'],
            ],
            'personalizationSections' => [
                ['name' => 'Bienvenida', 'status' => 'Visible', 'status_tone' => 'success', 'description' => 'Hero principal, subtitulo y CTA del sitio publico.'],
                ['name' => 'Servicios', 'status' => 'Pendiente', 'status_tone' => 'warning', 'description' => 'Tarjetas de servicios y destacados de especialidades.'],
                ['name' => 'Contacto', 'status' => 'Visible', 'status_tone' => 'success', 'description' => 'Telefonos, direccion, horarios y respuestas del formulario publico.'],
            ],
            'horarios' => [
                ['id' => 1, 'doctor' => 'Dra. Sofia Cardenas', 'fecha' => now()->toDateString(), 'hora_inicio' => '08:00:00', 'hora_fin' => '12:00:00'],
                ['id' => 2, 'doctor' => 'Dr. Andres Molina', 'fecha' => now()->toDateString(), 'hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00'],
                ['id' => 3, 'doctor' => 'Dr. Daniel Mora', 'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '14:00:00', 'hora_fin' => '18:00:00'],
            ],
            'recordatorios' => [
                ['id' => 1, 'patient' => 'Maria Fernanda Vega', 'phone' => '+593 99 514 0927', 'appointment' => '2026-04-28 08:00', 'channel' => 'WhatsApp', 'status' => 'Pendiente', 'status_tone' => 'warning'],
                ['id' => 2, 'patient' => 'Daniel Salazar', 'phone' => '+593 92 345 6781', 'appointment' => '2026-04-28 09:00', 'channel' => 'WhatsApp', 'status' => 'Enviado', 'status_tone' => 'success'],
            ],
            'manualBookingOptions' => [
                ['doctor' => 'Dra. Sofia Cardenas', 'specialty' => 'Pediatria', 'slot' => '28/04/2026 14:00', 'state' => 'Disponible', 'state_tone' => 'success'],
                ['doctor' => 'Dr. Andres Molina', 'specialty' => 'Medicina general', 'slot' => '28/04/2026 15:20', 'state' => 'Ultimo cupo', 'state_tone' => 'warning'],
            ],
            'appointmentChanges' => [
                ['patient' => 'Lucia Vega', 'doctor' => 'Dra. Sofia Cardenas', 'from' => '28/04/2026 08:00', 'to' => '28/04/2026 09:00', 'status' => 'Pendiente aprobacion', 'status_tone' => 'warning'],
                ['patient' => 'Daniel Salazar', 'doctor' => 'Dr. Andres Molina', 'from' => '29/04/2026 11:00', 'to' => '29/04/2026 12:00', 'status' => 'Confirmado', 'status_tone' => 'success'],
            ],
            'contactMessages' => [
                ['id' => 1, 'name' => 'Roberto Ibarra', 'email' => 'roberto.ibarra@example.com', 'phone' => '+593 95 000 1111', 'subject' => 'Disponibilidad de laboratorio', 'status' => 'Nuevo', 'status_tone' => 'warning', 'received_at' => '28/04/2026 07:20', 'message' => 'Buenas tardes, quisiera consultar la disponibilidad para realizar una prueba de laboratorio el día de mañana temprano. Saludos cordiales.'],
                ['id' => 2, 'name' => 'Monica Franco', 'email' => 'monica.franco@example.com', 'phone' => '+593 98 100 2333', 'subject' => 'Reagendar chequeo', 'status' => 'Leido', 'status_tone' => 'success', 'received_at' => '27/04/2026 18:14', 'message' => 'Estimados, solicito apoyo para reagendar mi chequeo de control médico debido a un cruce de horarios laborales. Muchas gracias.'],
            ],
            'adminProfile' => [
                'name' => 'Administrador Demo',
                'email' => 'admin.demo@example.com',
                'phone' => '+593 99 555 1212',
                'position' => 'Administrador general',
                'clinic_address' => 'Av. Principal y Calle 10, Quito',
                'summary' => 'Coordina usuarios, pagos, horarios y solicitudes publicas del sistema.',
            ],
        ];
    }

    private function pacienteDataDefaults(): array
    {
        return [
            'totalCitas' => 3,
            'totalCitasRealizadas' => 12,
            'totalCitasPendientes' => 1,
            'totalPagos' => 4,
            'pagosPendientes' => 1,
            'pagosPagados' => 3,
            'citas' => [
                ['id' => 1, 'specialty' => 'Pediatria', 'doctor' => 'Dra. Sofia Cardenas', 'date' => '30/04/2026', 'time' => '10:00', 'status' => 'Confirmada', 'status_tone' => 'info', 'priority_label' => 'Media', 'priority_badge' => 'bg-amber-100 text-amber-700', 'motivo' => 'Dolor al tragar desde hace tres dias, con congestion y malestar durante la noche.'],
                ['id' => 2, 'specialty' => 'Laboratorio clinico', 'doctor' => 'Orden generada por doctor', 'date' => '02/05/2026', 'time' => '08:30', 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Media', 'priority_badge' => 'bg-amber-100 text-amber-700', 'motivo' => 'Examen de control de rutina.'],
                ['id' => 3, 'specialty' => 'Medicina general', 'doctor' => 'Dr. Andres Molina', 'date' => '06/05/2026', 'time' => '16:20', 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Baja', 'priority_badge' => 'bg-green-100 text-green-700', 'motivo' => 'Control rutinario.'],
            ],
            'payments' => [
                ['id' => 1, 'folio' => 'PAG-2026-9001', 'description' => 'Consulta general', 'amount' => '$45.00', 'status' => 'Pagado', 'status_tone' => 'success', 'method' => 'Transferencia', 'date' => '26/04/2026 18:20'],
                ['id' => 2, 'folio' => 'PAG-2026-9002', 'description' => 'Control cardiologia', 'amount' => '$35.00', 'status' => 'En verificacion', 'status_tone' => 'info', 'method' => 'Deposito', 'date' => '27/04/2026 09:40'],
                ['id' => 3, 'folio' => 'PAG-2026-9003', 'description' => 'Examen laboratorio', 'amount' => '$28.00', 'status' => 'Pendiente', 'status_tone' => 'warning', 'method' => 'Pendiente de carga', 'date' => '28/04/2026 07:50'],
            ],
            'historyEntries' => [
                ['id' => 1, 'date' => '2026-03-15', 'doctor' => 'Dr. Carlos Ruiz', 'specialty' => 'Medicina general', 'summary' => 'Chequeo de rutina con controles basales normales.'],
                ['id' => 2, 'date' => '2026-02-04', 'doctor' => 'Dra. Sofia Cardenas', 'specialty' => 'Pediatria', 'summary' => 'Control preventivo de crecimiento y recomendaciones.'],
            ],
            'results' => [
                ['id' => 1, 'code' => 'LAB-2026-101', 'exam' => 'Hemograma completo', 'doctor' => 'Dr. Andres Molina', 'delivered_at' => '28/04/2026 11:10', 'status' => 'Disponible', 'status_tone' => 'success'],
                ['id' => 2, 'code' => 'LAB-2026-092', 'exam' => 'Glucosa en ayunas', 'doctor' => 'Dr. Andres Molina', 'delivered_at' => '27/04/2026 17:05', 'status' => 'Disponible', 'status_tone' => 'success'],
            ],
            'labOrders' => [
                ['id' => 1, 'exam' => 'Hemograma completo', 'origin' => 'Con orden medica', 'status' => 'Resultado listo', 'status_tone' => 'success'],
                ['id' => 2, 'exam' => 'Glucosa en ayunas', 'origin' => 'Rutina', 'status' => 'Resultado listo', 'status_tone' => 'success'],
            ],
            'patientProfile' => [
                'name' => 'Maria Fernanda Vega',
                'email' => 'maria.fernanda@example.com',
                'phone' => '+593 99 514 0927',
                'document' => '1723456789',
                'blood_type' => 'O+',
                'allergies' => 'Ninguna reportada',
                'emergency_contact' => 'Daniel Salazar - +593 98 222 3344',
            ],
            'dependientes' => [
                ['id' => 1, 'name' => 'Lucia Vega', 'dni' => '1756789012', 'birth_date' => '2018-05-10', 'relationship' => 'Hijo/a', 'status' => 'active'],
            ],
        ];
    }

    private function doctorDataDefaults(): array
    {
        return [
            'estadisticas' => [
                'pacientes_atendidos' => 145,
                'citas_pendientes' => 12,
                'calificacion' => 4.8,
                'citas_hoy' => 7,
                'citas_realizadas' => 4,
            ],
            'citasHoy' => [
                ['id' => 1, 'time' => '09:00', 'patient' => 'Lucia Vega', 'sex' => 'Femenino', 'age' => 8, 'status' => 'En sala de espera', 'status_tone' => 'info', 'priority_label' => 'Media', 'priority_badge' => 'bg-amber-100 text-amber-700', 'reason' => 'Dolor al tragar desde hace tres dias, con congestion y malestar durante la noche.'],
                ['id' => 2, 'time' => '10:30', 'patient' => 'Daniel Salazar', 'sex' => 'Masculino', 'age' => 37, 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Baja', 'priority_badge' => 'bg-green-100 text-green-700', 'reason' => 'Control de presion arterial'],
            ],
            'patients' => [
                ['id' => 10, 'name' => 'Lucia Vega', 'document' => '1756789012', 'age' => 8, 'blood_type' => 'O+', 'last_visit' => '22/04/2026'],
                ['id' => 11, 'name' => 'Daniel Salazar', 'document' => '0923456781', 'age' => 37, 'blood_type' => 'A+', 'last_visit' => '18/04/2026'],
            ],
            'prescriptions' => [
                ['id' => 1, 'patient' => 'Lucia Vega', 'specialty' => 'Pediatria', 'date' => '2026-04-22', 'time' => '09:00', 'pdf' => 'Disponible'],
                ['id' => 2, 'patient' => 'Daniel Salazar', 'specialty' => 'Medicina general', 'date' => '2026-04-18', 'time' => '10:30', 'pdf' => 'Disponible'],
            ],
            'weeklyAgenda' => [
                ['day' => 'Lunes', 'date' => '28/04', 'blocks' => ['08:00-10:00 Consultas', '10:30-12:00 Seguimientos', '15:00-17:00 Controles']],
                ['day' => 'Martes', 'date' => '29/04', 'blocks' => ['08:00-09:30 Laboratorio', '10:00-13:00 Consulta externa']],
            ],
            'scheduleBlocks' => [
                ['id' => 1, 'day' => 'Lunes', 'range' => '08:00 - 17:00', 'slots' => '14 cupos', 'state' => 'Operativo'],
                ['id' => 2, 'day' => 'Martes', 'range' => '08:00 - 16:00', 'slots' => '12 cupos', 'state' => 'Operativo'],
            ],
            'doctorProfile' => [
                'name' => 'Dra. Sofia Cardenas',
                'email' => 'sofia.cardenas@example.com',
                'phone' => '+593 99 700 1111',
                'specialty' => 'Pediatria',
                'license' => 'CMP-12345',
                'summary' => 'Especialista en control cardiovascular y seguimiento clinico preventivo.',
            ],
        ];
    }

    private function laboratorioDataDefaults(): array
    {
        return [
            'estadisticas' => [
                'pendientes' => 15,
                'muestras_hoy' => 8,
                'resultados_publicados' => 42,
            ],
            'ordenes' => [
                ['id' => 1, 'code' => 'LAB-2026-101', 'patient' => 'Lucia Vega', 'document' => '1756789012', 'exam' => 'Hemograma completo, Glucosa', 'status' => 'Pendiente muestra', 'status_tone' => 'warning', 'date' => '28/04/2026 08:15'],
                ['id' => 2, 'code' => 'LAB-2026-092', 'patient' => 'Daniel Salazar', 'document' => '0923456781', 'exam' => 'Perfil lipidico, TSH', 'status' => 'En proceso', 'status_tone' => 'info', 'date' => '28/04/2026 06:40'],
            ],
            'scheduleBlocks' => [
                ['id' => 1, 'day' => 'Lunes', 'range' => '07:00 - 16:00', 'state' => 'Recepcion y procesamiento', 'slots' => '18 turnos'],
                ['id' => 2, 'day' => 'Martes', 'range' => '07:00 - 16:00', 'state' => 'Recepcion y procesamiento', 'slots' => '18 turnos'],
            ],
        ];
    }
}

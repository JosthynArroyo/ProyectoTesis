<?php

namespace App\Http\Controllers;

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
        return $this->superadminView('demo.superadmin.administradores', 'administradores');
    }

    public function superadminUsuarios()
    {
        return $this->superadminView('demo.superadmin.usuarios', 'usuarios');
    }

    public function superadminSolicitudes()
    {
        return $this->superadminView('demo.superadmin.solicitudes', 'solicitudes');
    }

    public function superadminPersonalizacion()
    {
        return $this->superadminView('demo.superadmin.personalizacion', 'personalizacion');
    }

    public function superadminMantenimiento()
    {
        return $this->superadminView('demo.superadmin.mantenimiento', 'mantenimiento');
    }

    public function adminDashboard()
    {
        return $this->adminView('demo.admin.dashboard', 'dashboard');
    }

    public function adminUsuarios()
    {
        return $this->adminView('demo.admin.usuarios', 'usuarios');
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

    public function pacienteSolicitarExamen()
    {
        return $this->pacienteView('demo.paciente.solicitar-examen', 'solicitar-examen');
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

    private function superadminView(string $view, string $active)
    {
        return view($view, array_merge(
            $this->baseData('superadmin', ['activeSidebarKey' => $active]),
            $this->superadminData()
        ));
    }

    private function adminView(string $view, string $active)
    {
        return view($view, array_merge(
            $this->baseData('admin', ['activeSidebarKey' => $active]),
            $this->adminData()
        ));
    }

    private function pacienteView(string $view, string $active)
    {
        return view($view, array_merge(
            $this->baseData('paciente', ['activeSidebarKey' => $active]),
            $this->pacienteData()
        ));
    }

    private function doctorView(string $view, string $active)
    {
        return view($view, array_merge(
            $this->baseData('doctor', ['activeSidebarKey' => $active]),
            $this->doctorData()
        ));
    }

    private function laboratorioView(string $view, string $active)
    {
        return view($view, array_merge(
            $this->baseData('laboratorio', ['activeSidebarKey' => $active]),
            $this->laboratorioData()
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
                'roleLabel' => 'Superadmin',
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
                ['name' => 'Admin Principal', 'email' => 'admin.principal@demo.clinica', 'status' => 'Activo', 'created_at' => '12/02/2025'],
                ['name' => 'Admin Secundario', 'email' => 'admin.secundario@demo.clinica', 'status' => 'Activo', 'created_at' => '28/10/2025'],
                ['name' => 'Admin Operaciones', 'email' => 'operaciones@demo.clinica', 'status' => 'Activo', 'created_at' => '08/01/2026'],
            ],
            'usuarios' => [
                ['name' => 'Juan Perez', 'email' => 'juan.perez@demo.clinica', 'role' => 'Paciente', 'role_tone' => 'success', 'status' => 'Activo', 'status_tone' => 'success', 'created_at' => '05/04/2026'],
                ['name' => 'Dra. Maria Gonzalez', 'email' => 'maria.gonzalez@demo.clinica', 'role' => 'Doctor', 'role_tone' => 'info', 'status' => 'Activo', 'status_tone' => 'success', 'created_at' => '14/03/2026'],
                ['name' => 'Luis Fernandez', 'email' => 'luis.fernandez@demo.clinica', 'role' => 'Laboratorio', 'role_tone' => 'warning', 'status' => 'Activo', 'status_tone' => 'success', 'created_at' => '09/02/2026'],
                ['name' => 'Ana Torres', 'email' => 'ana.torres@demo.clinica', 'role' => 'Paciente', 'role_tone' => 'success', 'status' => 'Inactivo', 'status_tone' => 'warning', 'created_at' => '22/01/2026'],
            ],
            'solicitudes' => [
                ['admin' => 'Admin Principal', 'module' => 'Personalizacion', 'reason' => 'Actualizar banner de bienvenida y botones CTA.', 'status' => 'Pendiente', 'status_tone' => 'warning', 'submitted_at' => 'Hace 2 horas'],
                ['admin' => 'Admin Operaciones', 'module' => 'Servicios', 'reason' => 'Cambiar tarjetas informativas de laboratorio.', 'status' => 'Pendiente', 'status_tone' => 'warning', 'submitted_at' => 'Hoy, 09:10'],
                ['admin' => 'Admin Secundario', 'module' => 'Contacto', 'reason' => 'Editar telefonos y horarios publicados.', 'status' => 'Aprobada', 'status_tone' => 'success', 'submitted_at' => 'Ayer, 18:40'],
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
        ];
    }

    private function adminData(): array
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
                ['time' => '08:00', 'patient' => 'Juan Perez', 'doctor' => 'Dra. Maria Gonzalez', 'specialty' => 'Cardiologia', 'status' => 'Confirmada', 'status_tone' => 'info'],
                ['time' => '09:00', 'patient' => 'Ana Gomez', 'doctor' => 'Dr. Carlos Ruiz', 'specialty' => 'Medicina general', 'status' => 'Pendiente', 'status_tone' => 'warning'],
                ['time' => '10:30', 'patient' => 'Luis Martinez', 'doctor' => 'Dra. Sofia Herrera', 'specialty' => 'Pediatria', 'status' => 'Confirmada', 'status_tone' => 'info'],
                ['time' => '11:15', 'patient' => 'Elena Silva', 'doctor' => 'Dr. Daniel Mora', 'specialty' => 'Dermatologia', 'status' => 'Realizada', 'status_tone' => 'success'],
            ],
            'recentPayments' => [
                ['reference' => 'TRX-1001', 'patient' => 'Juan Perez', 'amount' => '$45.00', 'age' => 'Hace 1 hora'],
                ['reference' => 'TRX-1002', 'patient' => 'Ana Gomez', 'amount' => '$35.00', 'age' => 'Hace 2 horas'],
                ['reference' => 'TRX-1003', 'patient' => 'Carlos Vega', 'amount' => '$60.00', 'age' => 'Hace 4 horas'],
                ['reference' => 'TRX-1004', 'patient' => 'Maria Leon', 'amount' => '$28.00', 'age' => 'Hace 5 horas'],
            ],
            'usuarios' => [
                ['name' => 'Juan Perez', 'email' => 'juan.perez@demo.clinica', 'role' => 'Paciente', 'role_tone' => 'success', 'status' => 'Activo', 'status_tone' => 'success', 'specialty' => 'Sin especialidad', 'last_login' => 'Hace 10 min', 'registered_at' => '05/04/2026'],
                ['name' => 'Dra. Maria Gonzalez', 'email' => 'maria.gonzalez@demo.clinica', 'role' => 'Doctor', 'role_tone' => 'info', 'status' => 'Activo', 'status_tone' => 'success', 'specialty' => 'Cardiologia', 'last_login' => 'Hace 35 min', 'registered_at' => '14/03/2026'],
                ['name' => 'Luis Fernandez', 'email' => 'luis.fernandez@demo.clinica', 'role' => 'Laboratorio', 'role_tone' => 'warning', 'status' => 'Activo', 'status_tone' => 'success', 'specialty' => 'Procesamiento clinico', 'last_login' => 'Hace 50 min', 'registered_at' => '09/02/2026'],
                ['name' => 'Ana Torres', 'email' => 'ana.torres@demo.clinica', 'role' => 'Paciente', 'role_tone' => 'success', 'status' => 'Inactivo', 'status_tone' => 'warning', 'specialty' => 'Sin especialidad', 'last_login' => 'Hace 3 dias', 'registered_at' => '22/01/2026'],
            ],
            'paymentTotals' => [
                'pendiente' => 6,
                'en_verificacion' => 4,
                'rechazado' => 1,
                'pagado' => 28,
                'anulado' => 0,
            ],
            'payments' => [
                ['id' => 'P-001', 'folio' => 'FOL-2026-1001', 'patient' => 'Juan Perez', 'appointment' => 'Consulta general', 'amount' => '$45.00', 'method' => 'TRANSFERENCIA', 'status' => 'Pagado', 'status_tone' => 'success', 'date' => '28/04/2026 08:40'],
                ['id' => 'P-002', 'folio' => 'FOL-2026-1002', 'patient' => 'Ana Gomez', 'appointment' => 'Pediatria', 'amount' => '$35.00', 'method' => 'EFECTIVO', 'status' => 'En verificacion', 'status_tone' => 'info', 'date' => '28/04/2026 09:10'],
                ['id' => 'P-003', 'folio' => 'FOL-2026-1003', 'patient' => 'Carlos Vega', 'appointment' => 'Dermatologia', 'amount' => '$60.00', 'method' => 'TARJETA', 'status' => 'Pendiente', 'status_tone' => 'warning', 'date' => '27/04/2026 17:20'],
            ],
            'historialEntries' => [
                ['patient' => 'Juan Perez', 'doctor' => 'Dr. Carlos Ruiz', 'specialty' => 'Medicina general', 'date' => '28/04/2026 08:00', 'summary' => 'Chequeo de rutina y control de presion arterial.'],
                ['patient' => 'Ana Gomez', 'doctor' => 'Dra. Sofia Herrera', 'specialty' => 'Pediatria', 'date' => '27/04/2026 15:30', 'summary' => 'Revision de crecimiento con observaciones clinicas firmadas.'],
                ['patient' => 'Maria Leon', 'doctor' => 'Dr. Daniel Mora', 'specialty' => 'Dermatologia', 'date' => '26/04/2026 11:45', 'summary' => 'Evaluacion de seguimiento por tratamiento topico.'],
            ],
            'personalizationSections' => [
                ['name' => 'Bienvenida', 'status' => 'Visible', 'status_tone' => 'success', 'description' => 'Hero principal, subtitulo y CTA del sitio publico.'],
                ['name' => 'Servicios', 'status' => 'Pendiente', 'status_tone' => 'warning', 'description' => 'Tarjetas de servicios y destacados de especialidades.'],
                ['name' => 'Contacto', 'status' => 'Visible', 'status_tone' => 'success', 'description' => 'Telefonos, direccion, horarios y respuestas del formulario publico.'],
            ],
            'horarios' => [
                ['day' => 'Lunes', 'range' => '08:00 - 17:00', 'note' => '6 doctores activos'],
                ['day' => 'Martes', 'range' => '08:00 - 17:00', 'note' => '7 doctores activos'],
                ['day' => 'Miercoles', 'range' => '08:00 - 19:00', 'note' => 'Guardia extendida'],
                ['day' => 'Jueves', 'range' => '08:00 - 17:00', 'note' => 'Operativa normal'],
                ['day' => 'Viernes', 'range' => '08:00 - 17:00', 'note' => 'Laboratorio completo'],
            ],
            'recordatorios' => [
                ['patient' => 'Juan Perez', 'phone' => '+593 99 123 4567', 'appointment' => '28/04/2026 08:00', 'channel' => 'WhatsApp', 'status' => 'Pendiente', 'status_tone' => 'warning'],
                ['patient' => 'Ana Gomez', 'phone' => '+593 98 765 4321', 'appointment' => '28/04/2026 09:00', 'channel' => 'WhatsApp', 'status' => 'Enviado', 'status_tone' => 'success'],
                ['patient' => 'Carlos Vega', 'phone' => '+593 96 441 2222', 'appointment' => '28/04/2026 10:30', 'channel' => 'Revision manual', 'status' => 'Sin telefono valido', 'status_tone' => 'danger'],
            ],
            'manualBookingOptions' => [
                ['doctor' => 'Dra. Maria Gonzalez', 'specialty' => 'Cardiologia', 'slot' => '28/04/2026 14:00', 'state' => 'Disponible', 'state_tone' => 'success'],
                ['doctor' => 'Dr. Carlos Ruiz', 'specialty' => 'Medicina general', 'slot' => '28/04/2026 15:20', 'state' => 'Ultimo cupo', 'state_tone' => 'warning'],
                ['doctor' => 'Dra. Sofia Herrera', 'specialty' => 'Pediatria', 'slot' => '29/04/2026 09:40', 'state' => 'Disponible', 'state_tone' => 'success'],
            ],
            'appointmentChanges' => [
                ['patient' => 'Juan Perez', 'doctor' => 'Dra. Maria Gonzalez', 'from' => '28/04/2026 08:00', 'to' => '28/04/2026 09:00', 'status' => 'Pendiente aprobacion', 'status_tone' => 'warning'],
                ['patient' => 'Ana Gomez', 'doctor' => 'Dr. Carlos Ruiz', 'from' => '29/04/2026 11:00', 'to' => '29/04/2026 12:00', 'status' => 'Confirmado', 'status_tone' => 'success'],
                ['patient' => 'Maria Leon', 'doctor' => 'Dr. Daniel Mora', 'from' => '30/04/2026 16:00', 'to' => '02/05/2026 09:30', 'status' => 'Observado', 'status_tone' => 'info'],
            ],
            'contactMessages' => [
                ['name' => 'Roberto Ibarra', 'email' => 'roberto.ibarra@mail.test', 'phone' => '+593 95 000 1111', 'subject' => 'Disponibilidad de laboratorio', 'status' => 'Nuevo', 'status_tone' => 'warning', 'received_at' => '28/04/2026 07:20'],
                ['name' => 'Monica Franco', 'email' => 'monica.franco@mail.test', 'phone' => '+593 98 100 2333', 'subject' => 'Reagendar chequeo', 'status' => 'Leido', 'status_tone' => 'success', 'received_at' => '27/04/2026 18:14'],
                ['name' => 'Diego Salazar', 'email' => 'diego.salazar@mail.test', 'phone' => '-', 'subject' => 'Tarifas de examen clinico', 'status' => 'Nuevo', 'status_tone' => 'warning', 'received_at' => '27/04/2026 11:03'],
            ],
            'adminProfile' => [
                'name' => 'Administrador Demo',
                'email' => 'admin.demo@clinica.test',
                'phone' => '+593 99 555 1212',
                'position' => 'Administrador general',
                'clinic_address' => 'Av. Principal y Calle 10, Quito',
                'summary' => 'Coordina usuarios, pagos, horarios y solicitudes publicas del sistema.',
            ],
        ];
    }

    private function pacienteData(): array
    {
        return [
            'totalCitas' => 3,
            'totalCitasRealizadas' => 12,
            'totalCitasPendientes' => 1,
            'totalPagos' => 4,
            'pagosPendientes' => 1,
            'pagosPagados' => 3,
            'citas' => [
                ['specialty' => 'Cardiologia', 'doctor' => 'Dra. Maria Gonzalez', 'date' => '30/04/2026', 'time' => '10:00', 'status' => 'Confirmada', 'status_tone' => 'info', 'priority_label' => 'Baja', 'priority_badge' => 'bg-green-100 text-green-700'],
                ['specialty' => 'Laboratorio clinico', 'doctor' => 'Orden generada por doctor', 'date' => '02/05/2026', 'time' => '08:30', 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Media', 'priority_badge' => 'bg-amber-100 text-amber-700'],
                ['specialty' => 'Dermatologia', 'doctor' => 'Dr. Daniel Mora', 'date' => '06/05/2026', 'time' => '16:20', 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Baja', 'priority_badge' => 'bg-green-100 text-green-700'],
            ],
            'payments' => [
                ['folio' => 'PAG-2026-9001', 'description' => 'Consulta general', 'amount' => '$45.00', 'status' => 'Pagado', 'status_tone' => 'success', 'method' => 'Transferencia', 'date' => '26/04/2026 18:20'],
                ['folio' => 'PAG-2026-9002', 'description' => 'Control cardiologia', 'amount' => '$35.00', 'status' => 'En verificacion', 'status_tone' => 'info', 'method' => 'Deposito', 'date' => '27/04/2026 09:40'],
                ['folio' => 'PAG-2026-9003', 'description' => 'Examen laboratorio', 'amount' => '$28.00', 'status' => 'Pendiente', 'status_tone' => 'warning', 'method' => 'Pendiente de carga', 'date' => '28/04/2026 07:50'],
            ],
            'historyEntries' => [
                ['date' => '15/03/2026', 'doctor' => 'Dr. Carlos Ruiz', 'specialty' => 'Medicina general', 'summary' => 'Chequeo de rutina con controles basales normales.'],
                ['date' => '04/02/2026', 'doctor' => 'Dra. Maria Gonzalez', 'specialty' => 'Cardiologia', 'summary' => 'Control preventivo y recomendaciones de seguimiento.'],
                ['date' => '12/12/2025', 'doctor' => 'Dr. Daniel Mora', 'specialty' => 'Dermatologia', 'summary' => 'Revision de tratamiento y ajuste de prescripcion.'],
            ],
            'results' => [
                ['code' => 'LAB-2026-101', 'exam' => 'Hemograma completo', 'doctor' => 'Dr. Carlos Ruiz', 'delivered_at' => '28/04/2026 11:10', 'status' => 'Disponible', 'status_tone' => 'success'],
                ['code' => 'LAB-2026-092', 'exam' => 'Perfil lipidico', 'doctor' => 'Dra. Maria Gonzalez', 'delivered_at' => '27/04/2026 17:05', 'status' => 'Disponible', 'status_tone' => 'success'],
                ['code' => 'LAB-2026-087', 'exam' => 'TSH', 'doctor' => 'Dr. Carlos Ruiz', 'delivered_at' => 'En proceso', 'status' => 'En analisis', 'status_tone' => 'info'],
            ],
            'labOrders' => [
                ['exam' => 'Hemograma completo', 'origin' => 'Con orden medica', 'status' => 'Pendiente de toma', 'status_tone' => 'warning'],
                ['exam' => 'Perfil lipidico', 'origin' => 'Rutina', 'status' => 'Resultado listo', 'status_tone' => 'success'],
            ],
            'patientProfile' => [
                'name' => 'Paciente Demo',
                'email' => 'paciente.demo@clinica.test',
                'phone' => '+593 99 111 2233',
                'document' => '0912345678',
                'blood_type' => 'O+',
                'allergies' => 'Ninguna reportada',
                'emergency_contact' => 'Maria Demo - +593 98 222 3344',
            ],
        ];
    }

    private function doctorData(): array
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
                ['time' => '09:00', 'patient' => 'Juan Perez', 'sex' => 'Masculino', 'age' => 45, 'status' => 'En sala de espera', 'status_tone' => 'info', 'priority_label' => 'Media', 'priority_badge' => 'bg-amber-100 text-amber-700', 'reason' => 'Control de presion arterial'],
                ['time' => '10:30', 'patient' => 'Ana Gomez', 'sex' => 'Femenino', 'age' => 8, 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Baja', 'priority_badge' => 'bg-green-100 text-green-700', 'reason' => 'Control pediatrico'],
                ['time' => '11:45', 'patient' => 'Carlos Vega', 'sex' => 'Masculino', 'age' => 32, 'status' => 'Confirmada', 'status_tone' => 'info', 'priority_label' => 'Alta', 'priority_badge' => 'bg-rose-100 text-rose-700', 'reason' => 'Dolor toracico leve'],
                ['time' => '14:15', 'patient' => 'Elena Silva', 'sex' => 'Femenino', 'age' => 51, 'status' => 'Pendiente', 'status_tone' => 'warning', 'priority_label' => 'Media', 'priority_badge' => 'bg-amber-100 text-amber-700', 'reason' => 'Control de glicemia'],
            ],
            'patients' => [
                ['name' => 'Juan Perez', 'document' => '0912345678', 'age' => 45, 'blood_type' => 'O+', 'last_visit' => '22/04/2026'],
                ['name' => 'Ana Gomez', 'document' => '0923456789', 'age' => 8, 'blood_type' => 'A+', 'last_visit' => '18/04/2026'],
                ['name' => 'Carlos Vega', 'document' => '0934567890', 'age' => 32, 'blood_type' => 'B+', 'last_visit' => '11/04/2026'],
                ['name' => 'Elena Silva', 'document' => '0945678901', 'age' => 51, 'blood_type' => 'AB+', 'last_visit' => '07/04/2026'],
                ['name' => 'Monica Franco', 'document' => '0956789012', 'age' => 29, 'blood_type' => 'O-', 'last_visit' => '04/04/2026'],
                ['name' => 'Diego Salazar', 'document' => '0967890123', 'age' => 37, 'blood_type' => 'A-', 'last_visit' => '29/03/2026'],
            ],
            'prescriptions' => [
                ['patient' => 'Juan Perez', 'specialty' => 'Cardiologia', 'date' => '22/04/2026', 'time' => '09:00', 'pdf' => 'Disponible'],
                ['patient' => 'Ana Gomez', 'specialty' => 'Pediatria', 'date' => '18/04/2026', 'time' => '10:30', 'pdf' => 'Disponible'],
                ['patient' => 'Carlos Vega', 'specialty' => 'Medicina general', 'date' => '11/04/2026', 'time' => '14:00', 'pdf' => 'Disponible'],
            ],
            'weeklyAgenda' => [
                ['day' => 'Lunes', 'date' => '28/04', 'blocks' => ['08:00-10:00 Consultas', '10:30-12:00 Seguimientos', '15:00-17:00 Controles']],
                ['day' => 'Martes', 'date' => '29/04', 'blocks' => ['08:00-09:30 Laboratorio', '10:00-13:00 Consulta externa']],
                ['day' => 'Miercoles', 'date' => '30/04', 'blocks' => ['08:00-11:00 Consulta externa', '14:00-16:00 Interconsultas']],
                ['day' => 'Jueves', 'date' => '01/05', 'blocks' => ['09:00-12:00 Seguimiento', '15:00-17:00 Teleconsulta']],
                ['day' => 'Viernes', 'date' => '02/05', 'blocks' => ['08:00-10:00 Consulta externa', '10:30-12:00 Juntas medicas']],
            ],
            'scheduleBlocks' => [
                ['day' => 'Lunes', 'range' => '08:00 - 17:00', 'slots' => '14 cupos', 'state' => 'Operativo'],
                ['day' => 'Martes', 'range' => '08:00 - 16:00', 'slots' => '12 cupos', 'state' => 'Operativo'],
                ['day' => 'Miercoles', 'range' => '08:00 - 17:00', 'slots' => '14 cupos', 'state' => 'Operativo'],
                ['day' => 'Jueves', 'range' => '09:00 - 17:00', 'slots' => '12 cupos', 'state' => 'Operativo'],
                ['day' => 'Viernes', 'range' => '08:00 - 15:00', 'slots' => '10 cupos', 'state' => 'Operativo'],
            ],
            'doctorProfile' => [
                'name' => 'Doctor Demo',
                'email' => 'doctor.demo@clinica.test',
                'phone' => '+593 99 700 1111',
                'specialty' => 'Cardiologia',
                'license' => 'CMP-12345',
                'summary' => 'Especialista en control cardiovascular y seguimiento clinico preventivo.',
            ],
        ];
    }

    private function laboratorioData(): array
    {
        return [
            'estadisticas' => [
                'pendientes' => 15,
                'muestras_hoy' => 8,
                'resultados_publicados' => 42,
            ],
            'ordenes' => [
                ['code' => 'LAB-2026-101', 'patient' => 'Luis Fernandez', 'document' => '0987654321', 'exam' => 'Hemograma completo, Glucosa', 'status' => 'Pendiente muestra', 'status_tone' => 'warning', 'date' => '28/04/2026 08:15'],
                ['code' => 'LAB-2026-092', 'patient' => 'Paciente Demo 2', 'document' => '0912345678', 'exam' => 'Perfil lipidico, TSH', 'status' => 'En proceso', 'status_tone' => 'info', 'date' => '28/04/2026 06:40'],
                ['code' => 'LAB-2026-085', 'patient' => 'Paciente Demo 3', 'document' => '0956781234', 'exam' => 'Hemoglobina glicosilada', 'status' => 'Completado', 'status_tone' => 'success', 'date' => '27/04/2026 15:10'],
            ],
            'scheduleBlocks' => [
                ['day' => 'Lunes', 'range' => '07:00 - 16:00', 'state' => 'Recepcion y procesamiento', 'slots' => '18 turnos'],
                ['day' => 'Martes', 'range' => '07:00 - 16:00', 'state' => 'Recepcion y procesamiento', 'slots' => '18 turnos'],
                ['day' => 'Miercoles', 'range' => '07:00 - 18:00', 'state' => 'Jornada extendida', 'slots' => '22 turnos'],
                ['day' => 'Jueves', 'range' => '07:00 - 16:00', 'state' => 'Recepcion y procesamiento', 'slots' => '18 turnos'],
                ['day' => 'Viernes', 'range' => '07:00 - 15:00', 'state' => 'Entrega de resultados', 'slots' => '14 turnos'],
            ],
        ];
    }
}

@extends('layouts.doctor')
@section('title', 'Panel del doctor - '.$clinicIdentity->name())
@section('activeSidebar', 'dashboard')
@section('header-title','Panel medico')
@section('header-subtitle','Resumen del periodo y agenda activa')

@php
    $period = data_get($dashboard, 'filters.period', '30d');
    $options = app(\App\Services\DashboardAnalyticsService::class)->periodOptions();
@endphp

@push('scripts')
    @vite(['resources/js/dashboard-doctor.js'])
@endpush

@section('main')
    <div
        class="space-y-6"
        data-dashboard-page
        data-dashboard-endpoint="{{ route('doctor.dashboard.data') }}"
        data-dashboard-role="doctor"
    >
        <script type="application/json" data-dashboard-state>@json($dashboard)</script>

        @if(session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        <section class="card p-5">
            <form method="GET" action="{{ route('doctor.dashboard') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:gap-4" data-dashboard-filters-form>
                <div class="min-w-0 lg:w-56">
                    <label class="form-label" for="period">Periodo</label>
                    <select id="period" name="period" class="form-select">
                        @foreach($options as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:ml-auto">
                    <button class="btn btn-primary w-full lg:w-auto" type="submit">
                        <i class="ri-refresh-line"></i> Actualizar
                    </button>
                </div>
            </form>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <x-dashboard.chart-card
                class="lg:col-span-1"
                chart-key="appointments_status"
                chart-type="donut"
                title="Mis citas por estado"
                subtitle="Solo tus citas, con estados reales."
            />
            <x-dashboard.chart-card
                class="lg:col-span-1"
                chart-key="patients_timeline"
                chart-type="area"
                title="Pacientes atendidos"
                subtitle="Pacientes unicos atendidos por periodo."
            />
            <x-dashboard.chart-card
                class="lg:col-span-2"
                chart-key="appointments_status_timeline"
                chart-type="bar"
                title="Agenda de mis citas por día"
                subtitle="Comparación apilada del flujo de trabajo por día."
            />
            <x-dashboard.chart-card
                class="lg:col-span-1"
                chart-key="documents_type"
                chart-type="bar"
                title="Documentos generados"
                subtitle="Recetas, certificados y pedidos emitidos."
            />
            <x-dashboard.chart-card
                class="lg:col-span-1"
                chart-key="controls_status"
                chart-type="donut"
                title="Controles médicos"
                subtitle="Seguimientos agendados, completados o pendientes."
            />
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <article class="card p-6">
                <div class="page-header">
                    <div class="page-header__info">
                        <p class="text-xs uppercase tracking-widest text-gray-500">Agenda</p>
                        <h2>Citas recientes</h2>
                        <p>Actualizadas en tiempo real.</p>
                    </div>
                </div>
                <div class="mt-4 table-shell table-responsive-cards">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-citas">
                            @forelse($citas as $c)
                                <tr>
                                    <td data-label="Paciente">{{ $c->nombrePacienteReal() }}</td>
                                    <td data-label="Estado">
                                        <x-ui.badge :tone="$c->estado === 'pendiente' ? 'warning' : ($c->estado === 'realizada' ? 'success' : ($c->estado === 'confirmada' ? 'info' : 'danger'))">
                                            {{ $c->estado === 'no_se_presento' ? 'No se presento' : ucfirst($c->estado) }}
                                        </x-ui.badge>
                                    </td>
                                    <td data-label="Prioridad">
                                        @php
                                            $priorityTone = match($c->prioridad_nivel) {
                                                'ALTA' => 'danger',
                                                'MEDIA' => 'warning',
                                                default => 'neutral',
                                            };
                                        @endphp
                                        <x-ui.badge :tone="$priorityTone">{{ $c->prioridad_nivel ?? 'BAJA' }}</x-ui.badge>
                                        @if($c->prioridad_red_flag)
                                            <span class="badge danger">Red flag</span>
                                        @endif
                                    </td>
                                    <td data-label="Fecha">{{ \Illuminate\Support\Carbon::parse($c->fecha)->format('d/m/Y') }}</td>
                                    <td data-label="Hora">{{ $c->hora }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">Sin citas para hoy.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="space-y-4">
                <section class="card p-6 dashboard-list-card" data-dashboard-list="next_appointments">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Proximas citas</h3>
                        <i class="ri-arrow-right-up-line text-gray-400"></i>
                    </div>
                    <div class="mt-4 space-y-3" data-dashboard-list-body></div>
                    <div class="mt-4 hidden text-sm text-gray-500" data-dashboard-list-empty>No hay citas proximas.</div>
                </section>

                <section class="card p-6 dashboard-list-card" data-dashboard-list="follow_up_controls">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900">Controles proximos</h3>
                        <i class="ri-calendar-event-line text-gray-400"></i>
                    </div>
                    <div class="mt-4 space-y-3" data-dashboard-list-body></div>
                    <div class="mt-4 hidden text-sm text-gray-500" data-dashboard-list-empty>No hay controles programados.</div>
                </section>
            </aside>
        </section>
    </div>
@endsection

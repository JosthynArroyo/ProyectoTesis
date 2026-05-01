@extends('layouts.doctor')
@section('title', 'Panel del doctor - '.$clinicIdentity->name())
@section('activeSidebar', 'dashboard')
@section('header-title','Panel médico')
@section('header-subtitle','Resumen del día y agenda activa')

@push('head')
    <meta name="doctor-dashboard-data" content="{{ route('doctor.dashboard.data') }}">
    <meta name="user-id" content="{{ Auth::id() }}">
@endpush

@push('scripts')
    @vite(['resources/js/dashboard-doctor.js'])
@endpush

@section('main')
    <div class="space-y-6">
        @if(session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        <section class="stat-grid">
            <x-ui.stat label="Mis citas hoy" :value="$citasHoy" tone="teal">
                <x-slot:icon><i class="ri-calendar-line"></i></x-slot:icon>
                <p class="text-xs text-gray-500">Agenda del día</p>
            </x-ui.stat>
            <x-ui.stat label="Atendidas" :value="$citasRealizadas" tone="sky">
                <x-slot:icon><i class="ri-checkbox-circle-line"></i></x-slot:icon>
                <p class="text-xs text-gray-500">Citas completadas hoy</p>
            </x-ui.stat>
            <x-ui.stat label="Pendientes" :value="$citasPendientes" tone="amber">
                <x-slot:icon><i class="ri-timer-line"></i></x-slot:icon>
                <p class="text-xs text-gray-500">En espera de atención</p>
            </x-ui.stat>
            <x-ui.stat label="Total pacientes" :value="$totalPacientes ?? 0" tone="slate">
                <x-slot:icon><i class="ri-group-line"></i></x-slot:icon>
                <p class="text-xs text-gray-500">Únicos atendidos</p>
            </x-ui.stat>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
            <article class="card p-6">
                <div class="page-header">
                    <div class="page-header__info">
                        <p class="text-xs uppercase tracking-widest text-gray-500">Agenda</p>
                        <h2>Citas recientes</h2>
                        <p>Actualizadas en tiempo real</p>
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
                                    <td data-label="Paciente">{{ optional($c->paciente)->name ?? 'Sin paciente' }}</td>
                                    <td data-label="Estado">
                                        <x-ui.badge :tone="$c->estado === 'pendiente' ? 'warning' : ($c->estado === 'realizada' ? 'success' : ($c->estado === 'confirmada' ? 'info' : 'danger'))">
                                            {{ $c->estado === 'no_se_presento' ? 'No se presentó' : ucfirst($c->estado) }}
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

            <article class="card p-6">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-500">Últimas 2 horas</p>
                    <h2 class="mt-2 text-lg font-semibold text-gray-900">Resumen de citas</h2>
                </div>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <i class="ri-calendar-check-line text-teal-600"></i>
                            <span class="text-sm text-gray-600">Confirmadas</span>
                        </div>
                        <span class="text-base font-semibold text-gray-900" id="k-conf-2h">{{ $citasConfirmadas2h }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <i class="ri-checkbox-circle-line text-sky-600"></i>
                            <span class="text-sm text-gray-600">Atendidas</span>
                        </div>
                        <span class="text-base font-semibold text-gray-900" id="k-real-2h">{{ $citasRealizadas2h }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white/90 px-3 py-2">
                        <div class="flex items-center gap-2">
                            <i class="ri-close-circle-line text-rose-600"></i>
                            <span class="text-sm text-gray-600">Canceladas</span>
                        </div>
                        <span class="text-base font-semibold text-gray-900" id="k-canc-2h">{{ $citasCanceladas2h }}</span>
                    </div>
                </div>
                <div class="mt-6">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Actividad</p>
                    <h3 class="mt-2 text-base font-semibold text-gray-900">Actualizaciones</h3>
                    <div class="mt-3 space-y-3" id="updates"></div>
                </div>
            </article>
        </section>

        <section class="card p-6">
            <div class="page-header">
                <div class="page-header__info">
                    <p class="text-xs uppercase tracking-widest text-gray-500">En tiempo real</p>
                    <h2>Indicadores dinámicos</h2>
                </div>
                <div class="page-header__actions">
                    <x-ui.context-pill label="Agenda activa">
                        <x-slot:icon><i class="ri-calendar-line"></i></x-slot:icon>
                        {{ now()->format('d/m/Y') }}
                    </x-ui.context-pill>
                </div>
            </div>
            <div class="mt-4 stat-grid">
                <div class="card p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Hoy</p>
                    <p class="mt-2 text-2xl font-semibold" id="k-hoy">{{ $citasHoy }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Realizadas</p>
                    <p class="mt-2 text-2xl font-semibold" id="k-realizadas">{{ $citasRealizadas }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Pendientes</p>
                    <p class="mt-2 text-2xl font-semibold" id="k-pendientes">{{ $citasPendientes }}</p>
                </div>
                <div class="card p-4">
                    <p class="text-xs uppercase tracking-widest text-gray-500">Pacientes</p>
                    <p class="mt-2 text-2xl font-semibold" id="k-pacientes">{{ $totalPacientes ?? 0 }}</p>
                </div>
            </div>
        </section>
    </div>
@endsection

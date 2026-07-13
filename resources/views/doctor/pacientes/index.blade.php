@extends('layouts.doctor')
@section('title', 'Pacientes')
@section('activeSidebar', 'pacientes')
@section('header-title', 'Pacientes')
@section('header-subtitle', 'Expedientes y seguimiento clínico')

@push('styles')
    @vite('resources/css/doctor/pacientes-index.css')
@endpush

@section('main')
@php
    $sortLabels = [
        'recientes' => 'Última consulta reciente',
        'alfabetico' => 'Nombre A-Z',
        'laboratorios' => 'Más laboratorios pendientes',
    ];
@endphp

<section class="doctor-patients-page">
    <div class="doctor-patients-overview">
        <article class="doctor-patients-stat">
            <span class="doctor-patients-stat__icon doctor-patients-stat__icon--blue">
                <i class="ri-group-line"></i>
            </span>
            <div class="doctor-patients-stat__body">
                <p>Pacientes activos</p>
                <strong>{{ number_format($stats['total_active']) }}</strong>
            </div>
        </article>

        <article class="doctor-patients-stat">
            <span class="doctor-patients-stat__icon doctor-patients-stat__icon--amber">
                <i class="ri-flask-line"></i>
            </span>
            <div class="doctor-patients-stat__body">
                <p>Laboratorios pendientes</p>
                <strong>{{ number_format($stats['pending_labs']) }}</strong>
            </div>
        </article>

        <article class="doctor-patients-stat">
            <span class="doctor-patients-stat__icon doctor-patients-stat__icon--emerald">
                <i class="ri-stethoscope-line"></i>
            </span>
            <div class="doctor-patients-stat__body">
                <p>Consultas de hoy</p>
                <strong>{{ number_format($stats['today_visits']) }}</strong>
            </div>
        </article>

        <form class="doctor-patients-sort-card" method="GET" action="{{ route('doctor.pacientes.index') }}">
            <span class="doctor-patients-sort-card__label">Ordenar por</span>
            <label class="doctor-patients-select-shell" for="sort">
                <select id="sort" name="sort" onchange="this.form.submit()">
                    <option value="recientes" @selected($sort === 'recientes')>Última consulta reciente</option>
                    <option value="alfabetico" @selected($sort === 'alfabetico')>Nombre A-Z</option>
                    <option value="laboratorios" @selected($sort === 'laboratorios')>Más laboratorios pendientes</option>
                </select>
                <i class="ri-arrow-down-s-line"></i>
            </label>
        </form>
    </div>

    <section class="doctor-patients-list">
        <div class="doctor-patients-list__head">
            <div>
                <p class="doctor-patients-eyebrow">Casos clínicos</p>
                <h2>Listado de pacientes</h2>
            </div>
            <p class="doctor-patients-list__meta">{{ method_exists($patients, 'total') ? $patients->total() : $patients->count() }} expedientes - {{ $sortLabels[$sort] ?? $sortLabels['recientes'] }}</p>
        </div>

        @if($patients->count() === 0)
            <div class="doctor-patients-list__empty">
                <x-ui.empty-state title="Aún no tienes pacientes vinculados." message="Cuando atiendas o confirmes consultas aparecerán aquí para abrir su expediente clínico y continuar el seguimiento.">
                    <a class="btn btn-primary" href="{{ route('doctor.citas') }}">
                        <i class="ri-calendar-line"></i>
                        Ir a mis citas
                    </a>
                </x-ui.empty-state>
            </div>
        @else
            <div class="doctor-patients-table-wrap">
                <table class="doctor-patients-table">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Edad</th>
                            <th>Última atención</th>
                            <th>Contacto</th>
                            <th class="doctor-patients-table__actions-col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($patients as $patient)
                            <tr>
                                <td data-label="Paciente">
                                    <div class="doctor-patient-cell">
                                        <span class="doctor-patient-avatar doctor-patient-avatar--{{ $patient['avatar_tone'] }}">
                                            {{ $patient['initials'] }}
                                        </span>
                                        <div class="doctor-patient-cell__text">
                                            <strong>{{ $patient['name'] }}</strong>
                                            <p>ID: #{{ $patient['code'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Edad">
                                    {{ $patient['age'] !== null ? $patient['age'].' años' : 'No registrada' }}
                                </td>
                                <td data-label="Última atención">
                                    <div class="doctor-patient-last-visit">
                                        <strong>{{ $patient['last_visit_display'] }}</strong>
                                        <span class="doctor-patient-chip doctor-patient-chip--{{ $patient['last_visit_context_tone'] }}">
                                            {{ $patient['last_visit_context'] }}
                                        </span>
                                        <span class="doctor-patient-status doctor-patient-status--{{ $patient['last_visit_status_tone'] }}">
                                            {{ $patient['last_visit_status_label'] }}
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Contacto">
                                    <div class="doctor-patient-contact">
                                        <span>
                                            <i class="ri-mail-line"></i>
                                            {{ $patient['email'] ?: 'Sin correo registrado' }}
                                        </span>
                                        <span>
                                            <i class="ri-phone-line"></i>
                                            {{ $patient['phone'] ?: 'Sin teléfono registrado' }}
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Acciones">
                                    <div class="doctor-patient-actions">
                                        <a class="doctor-patient-link" href="{{ $patient['record_url'] }}">
                                            Ver expediente
                                            <i class="ri-arrow-right-line"></i>
                                        </a>
                                        @if($patient['pending_labs_count'] > 0)
                                            <span class="doctor-patient-actions__hint">{{ $patient['pending_labs_count'] }} pendientes</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($patients, 'links'))
                <div class="mt-6">
                    {{ $patients->links() }}
                </div>
            @endif
        @endif
    </section>
</section>
@endsection

@extends('layouts.demo')
@section('title', 'Reagendar cita - Demo')
@section('body-class', 'paciente-body--editar-cita')
@section('header-title','Reagendar cita')
@section('header-subtitle','Ajusta la fecha y hora (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-paciente-demo')
@endsection

@php
  $citaObj = new \App\Models\Cita();
  $citaObj->id = $cita->id;
  $citaObj->estado = strtolower($cita->status ?? 'pendiente');
  $citaObj->fecha = \Carbon\Carbon::parse($cita->date ?? now());
  $citaObj->hora = $cita->time ?? '08:00';
  $citaObj->motivo_consulta = 'Dolor de garganta y malestar general';
  
  $citaObj->setRelation('doctor', new \App\Models\User(['name' => $cita->doctor ?? 'Dr. Andrés Molina']));
  $citaObj->setRelation('especialidad', new \App\Models\Especialidad(['nombre' => $cita->specialty ?? 'Medicina General']));
@endphp

@section('main')
    <div class="space-y-6">
        <div class="panel-action-bar">
            <x-ui.context-pill label="Cita">
                <x-slot:icon><i class="ri-calendar-check-line"></i></x-slot:icon>
                #{{ $citaObj->id }}
            </x-ui.context-pill>
        </div>

        <div class="card p-6 bg-white">
            @if(session('error'))
                <x-ui.alert tone="error">{{ session('error') }}</x-ui.alert>
            @endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-gray-400">Doctor</div>
                    <div class="text-sm font-semibold text-gray-900">{{ optional($citaObj->doctor)->name }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-gray-400">Especialidad</div>
                    <div class="text-sm font-semibold text-gray-900">{{ optional($citaObj->especialidad)->nombre }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-widest text-gray-400">Estado</div>
                    <div class="text-sm">
                        <span class="badge {{ $citaObj->estado === 'pendiente' ? 'warning' : 'info' }}">
                            {{ $citaObj->estado === 'pendiente' ? 'En revisión' : 'Confirmada' }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('demo.paciente.editar-cita.update', $citaObj->id) }}" class="mt-6 space-y-5">
                @csrf
                @method('PUT')

                <section class="panel-form-section">
                    <div class="panel-form-section__header">
                        <div class="panel-form-section__heading">
                            <h2 class="panel-form-section__title">
                                <span class="panel-form-section__icon"><i class="ri-calendar-line"></i></span>
                                Nueva disponibilidad
                            </h2>
                            <p class="panel-form-section__hint">Selecciona una nueva fecha y una hora disponible para el mismo profesional.</p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="fecha" class="form-label">Nueva fecha</label>
                            <input id="fecha" type="date" name="fecha" value="{{ $citaObj->fecha->toDateString() }}" required class="form-input">
                        </div>

                        <div>
                            <label for="hora" class="form-label">Nueva hora</label>
                            <select id="hora" name="hora" required class="form-select">
                                <option value="08:00" @selected($citaObj->hora === '08:00')>08:00</option>
                                <option value="09:00" @selected($citaObj->hora === '09:00')>09:00</option>
                                <option value="10:00" @selected($citaObj->hora === '10:00')>10:00</option>
                                <option value="11:00" @selected($citaObj->hora === '11:00')>11:00</option>
                                <option value="14:00" @selected($citaObj->hora === '14:00')>14:00</option>
                                <option value="15:00" @selected($citaObj->hora === '15:00')>15:00</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="panel-form-section border-t border-gray-100 pt-5">
                    <div class="panel-form-section__header">
                        <div class="panel-form-section__heading">
                            <h2 class="panel-form-section__title">
                                <span class="panel-form-section__icon"><i class="ri-file-text-line"></i></span>
                                Motivo de consulta
                            </h2>
                            <p class="panel-form-section__hint">Describe el motivo en una sola línea.</p>
                        </div>
                    </div>

                    <div>
                        <label for="motivo_consulta" class="form-label">Motivo de consulta</label>
                        <input id="motivo_consulta" type="text" name="motivo_consulta" value="{{ $citaObj->motivo_consulta }}" required class="form-input">
                    </div>
                </section>

                <x-ui.form-actions>
                    <x-slot:left>
                        <a href="{{ route('demo.paciente.citas') }}" class="btn btn-ghost">
                            <i class="ri-arrow-left-line"></i> Cancelar
                        </a>
                    </x-slot>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line"></i> Guardar cambios
                    </button>
                </x-ui.form-actions>
            </form>
        </div>
    </div>
@endsection

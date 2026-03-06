@extends('layouts.doctor')
@section('title','Agenda semanal | Doctor')
@section('activeSidebar','agenda')
@section('header-title','Agenda semanal')
@section('header-subtitle','Consulta tus bloques disponibles')

@section('main')
  <section class="space-y-6">
    <div class="card p-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-slate-500">Agenda semanal</p>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Agenda</h1>
        <p class="text-slate-600">Consulta y navega por tus bloques disponibles.</p>
      </div>
    </div>

    @php($doctorId = auth()->id())
    <div class="card p-6 agenda-wrap"
         data-doctor="{{ $doctorId }}"
         data-slots-url="{{ route('api.doctor.slots',['doctor'=>$doctorId,'fecha'=>'YYYY-MM-DD']) }}">
      <div class="flex flex-wrap items-center gap-3">
        <button class="btn btn-outline" id="prevWeek" aria-label="Semana anterior"><i class="ri-arrow-left-line"></i></button>
        <div class="week-label text-sm font-semibold text-slate-700" id="weekLabel"></div>
        <button class="btn btn-outline" id="nextWeek" aria-label="Semana siguiente"><i class="ri-arrow-right-line"></i></button>
        <div class="flex-1"></div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <span>Ir a:</span>
          <input type="date" id="gotoDate" class="form-input">
        </label>
      </div>

      <div id="agendaHost" class="mt-6"></div>
    </div>
  </section>
@endsection

@push('scripts')
  @vite('resources/js/doctor/agenda.js')
@endpush
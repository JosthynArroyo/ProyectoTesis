@extends('layouts.doctor')
@section('title','Agenda semanal | Doctor')

@section('activeSidebar','agenda')

@push('head')
  @vite('resources/css/doctor/agenda.css')
@endpush

@section('content')
  @php($doctorId = auth()->id())
  <div class="agenda-wrap"
       data-doctor="{{ $doctorId }}"
       data-slots-url="{{ route('api.doctor.slots',['doctor'=>$doctorId,'fecha'=>'YYYY-MM-DD']) }}">
    <div class="agenda-toolbar">
      <button class="btn" id="prevWeek" aria-label="Semana anterior">←</button>
      <div class="week-label" id="weekLabel"></div>
      <button class="btn" id="nextWeek" aria-label="Semana siguiente">→</button>
      <div class="spacer"></div>
      <label class="datepick">
        <span>Ir a:</span>
        <input type="date" id="gotoDate">
      </label>
    </div>

    <div id="agendaHost"></div> {{-- aquí se renderiza la tabla --}}
  </div>
@endsection

@push('scripts')
  @vite('resources/js/doctor/agenda.js')
@endpush

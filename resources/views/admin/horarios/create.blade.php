{{-- resources/views/admin/horarios/create.blade.php --}}
@extends('layouts.admin')
@section('title','Crear horario')
@section('header-title','Nuevo horario')
@section('header-subtitle','Define rangos y franjas semanales')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div>
      <p class="text-xs uppercase tracking-widest text-slate-500">Nuevo horario</p>
      <h1 class="mt-2 text-2xl font-semibold text-slate-900">Crear horario</h1>
      <p class="text-slate-600">Define el rango de fechas, selecciona dÃ­as y asigna franjas.</p>
    </div>
  </section>


  <form action="{{ route('admin.horarios.store') }}" method="POST" class="card p-6" id="form-horario">
    @csrf

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div class="md:col-span-3">
        <label class="form-label" for="doctor_id">Doctor</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-stethoscope-line text-slate-400"></i>
          <select class="w-full bg-transparent text-sm" id="doctor_id" name="doctor_id" required>
            <option value="">Seleccione</option>
            @foreach($doctores as $d)
              <option value="{{ $d->id }}" {{ old('doctor_id') == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
        @error('doctor_id')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label" for="fecha_inicio">Desde</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-calendar-line text-slate-400"></i>
          <input class="w-full bg-transparent text-sm" type="date" id="fecha_inicio" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required>
        </div>
        @error('fecha_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>

      <div>
        <label class="form-label" for="fecha_fin">Hasta</label>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
          <i class="ri-calendar-event-line text-slate-400"></i>
          <input class="w-full bg-transparent text-sm" type="date" id="fecha_fin" name="fecha_fin" value="{{ old('fecha_fin') }}" required>
        </div>
        @error('fecha_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
      <div class="flex flex-wrap items-center gap-3">
        <span class="badge info"><i class="ri-repeat-line"></i> Repetir por dÃ­as</span>
        <p class="text-sm text-slate-500">Elige dÃ­as y define una franja global o por dÃ­a.</p>
      </div>

      @php $dias = [1=>'Lun',2=>'Mar',3=>'MiÃ©',4=>'Jue',5=>'Vie',6=>'SÃ¡b',7=>'Dom']; @endphp
      <div id="dias-wrap" class="mt-4 flex flex-wrap gap-2">
        @foreach($dias as $num=>$lbl)
          @php $checked = in_array($num, (array)old('dias', [1,2,3,4,5])); @endphp
          <label class="day-chip rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold {{ $checked ? 'active bg-teal-50 text-teal-700' : 'text-slate-500' }}">
            <input type="checkbox" name="dias[]" value="{{ $num }}" {{ $checked ? 'checked' : '' }}>
            <span>{{ $lbl }}</span>
          </label>
        @endforeach
      </div>
      @error('dias')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror

      <div class="mt-4 flex flex-wrap gap-2">
        <button type="button" class="btn-mini btn btn-outline" data-preset="lv">L-V</button>
        <button type="button" class="btn-mini btn btn-outline" data-preset="ld">L-D</button>
        <button type="button" class="btn-mini btn btn-outline" data-preset="sd">S-D</button>
        <button type="button" class="btn-mini btn btn-outline" data-preset="none">Ninguno</button>
      </div>

      <label class="mt-4 flex items-center gap-2 text-sm text-slate-600" for="misma_franja">
        <input type="hidden" name="misma_franja" value="0">
        <input type="checkbox" id="misma_franja" name="misma_franja" value="1" {{ old('misma_franja',1) ? 'checked' : '' }}>
        Usar la misma franja para todos los dÃ­as marcados
      </label>
      @error('misma_franja')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror

      <div id="franja-global" class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="form-label" for="hora_inicio">Hora inicio</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-time-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="time" id="hora_inicio" name="hora_inicio" step="1800" value="{{ old('hora_inicio') }}" required>
          </div>
          @error('hora_inicio')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="hora_fin">Hora fin</label>
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white/90 px-3 py-2">
            <i class="ri-time-line text-slate-400"></i>
            <input class="w-full bg-transparent text-sm" type="time" id="hora_fin" name="hora_fin" step="1800" value="{{ old('hora_fin') }}" required>
          </div>
          @error('hora_fin')<div class="text-xs text-rose-600">{{ $message }}</div>@enderror
        </div>
      </div>

      <div id="franjas-por-dia" class="mt-4 space-y-3" style="display:none">
        <div class="text-xs text-slate-500">Define horas por cada dÃ­a marcado.</div>
        @foreach($dias as $num=>$lbl)
          @php $row = old("horas.$num", ['inicio'=>null,'fin'=>null]); @endphp
          <div class="row-dia flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white/90 px-3 py-2" data-dia="{{ $num }}">
            <div class="row-dia__label text-sm font-semibold text-slate-600">{{ $lbl }}</div>
            <input class="form-input" type="time" name="horas[{{ $num }}][inicio]" step="1800" value="{{ $row['inicio'] }}" placeholder="hh:mm" required>
            <span class="row-dia__sep text-xs text-slate-400">a</span>
            <input class="form-input" type="time" name="horas[{{ $num }}][fin]" step="1800" value="{{ $row['fin'] }}" placeholder="hh:mm" required>
          </div>
        @endforeach
      </div>

      <p class="mt-4 text-sm text-slate-500" id="kpi"></p>
    </div>

    <div class="mt-6">
      <x-ui.form-actions>
        <x-slot:left>
          <a class="btn btn-ghost" href="{{ route('admin.horarios.index') }}">Volver</a>
        </x-slot>
        <button class="btn btn-primary" type="submit">Guardar</button>
      </x-ui.form-actions>
    </div>
  </form>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/horarios/create.js')
@endpush


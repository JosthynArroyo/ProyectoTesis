@extends($layout)

@section('title', 'Ajustar prioridad de cita')
@section('header-title', 'Ajustar prioridad')
@section('header-subtitle', 'Override manual con auditoria')

@section('main')
<div class="space-y-6">
  <section class="card p-6">
    <div class="space-y-2">
      <p class="text-xs uppercase tracking-widest text-slate-500">Cita #{{ $cita->id }}</p>
      <h1 class="text-2xl font-semibold text-slate-900">Actualizar prioridad</h1>
      <p class="text-slate-600">
        Paciente: <strong>{{ optional($cita->paciente)->name ?? 'Sin paciente' }}</strong> |
        Doctor: <strong>{{ optional($cita->doctor)->name ?? 'Sin doctor' }}</strong> |
        Fecha: <strong>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</strong> {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }}
      </p>
    </div>
  </section>

  @if(session('success'))
    <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
  @endif

  <section class="card p-6">
    <form method="POST" action="{{ $submitRoute }}" class="space-y-5">
      @csrf
      @method('PATCH')
      <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

      <div class="grid gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="prioridad_nivel">Nuevo nivel de prioridad</label>
          <select id="prioridad_nivel" name="prioridad_nivel" class="form-select" required>
            @foreach($niveles as $nivel)
              <option value="{{ $nivel }}" @selected(old('prioridad_nivel', $cita->prioridad_nivel) === $nivel)>{{ $nivel }}</option>
            @endforeach
          </select>
          @error('prioridad_nivel')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
          <p><strong>Actual:</strong> {{ $cita->prioridad_nivel }}</p>
          <p><strong>Fuente:</strong> {{ $cita->prioridad_fuente }}</p>
          <p>
            <strong>Red flag:</strong>
            {{ $cita->prioridad_red_flag ? 'Si'.($cita->prioridad_red_flag_tipo ? ' ('.$cita->prioridad_red_flag_tipo.')' : '') : 'No' }}
          </p>
        </div>
      </div>

      <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900">
          <input type="checkbox"
                 name="ignorar_red_flag"
                 value="1"
                 class="h-4 w-4"
                 @checked(old('ignorar_red_flag'))>
          Ignorar red flag de esta cita
        </label>
        <p class="mt-2 text-xs text-amber-800">
          Si bajas prioridad o ignoras un red flag, el comentario es obligatorio.
        </p>
      </div>

      <div>
        <label class="form-label" for="prioridad_comentario">Comentario de auditoria</label>
        <textarea id="prioridad_comentario"
                  name="prioridad_comentario"
                  rows="3"
                  maxlength="500"
                  class="form-textarea"
                  placeholder="Motivo del cambio manual...">{{ old('prioridad_comentario') }}</textarea>
        @error('prioridad_comentario')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
      </div>

      <x-ui.form-actions>
        <x-slot:left>
          <a href="{{ $cancelRoute }}" class="btn btn-ghost">Cancelar</a>
        </x-slot>
        <button type="submit" class="btn btn-primary">Guardar prioridad</button>
      </x-ui.form-actions>
    </form>
  </section>
</div>
@endsection


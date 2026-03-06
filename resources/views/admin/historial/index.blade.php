@extends('layouts.admin')
@section('title', 'Historial clínico')
@section('header-title','Historial clínico')
@section('header-subtitle','Notas SOAP firmadas')

@section('main')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-slate-500">Historial</p>
          <h1 class="mt-2 text-2xl font-semibold text-slate-900">Historial clínico</h1>
          <p class="text-slate-600">Consulta notas SOAP firmadas por paciente.</p>
        </div>
      </div>
      <form class="mt-4 flex flex-wrap items-end gap-3" method="GET" action="{{ route('admin.historial.index') }}">
        <div class="inline-control-shell flex-1">
          <i class="ri-search-line text-slate-400"></i>
          <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por paciente, cédula o correo..." class="w-full bg-transparent text-sm text-slate-700" required>
        </div>
        @error('q')<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
        <button class="btn btn-outline" type="submit">
          <i class="ri-filter-3-line"></i> Filtrar
        </button>
      </form>
    </section>

    @if(collect($notas ?? [])->isEmpty())
      <section class="card p-6">
        <x-ui.empty-state title="Sin notas registradas" message="No hay notas SOAP firmadas con los filtros actuales."></x-ui.empty-state>
      </section>
    @else
      <section class="card p-6">
        <div class="table-shell">
          <table class="table w-full">
            <thead>
              <tr>
                <th>Paciente</th>
                <th>Doctor</th>
                <th>Especialidad</th>
                <th>Fecha</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($notas as $nota)
                <tr>
                  <td>{{ optional($nota->cita->paciente)->name ?? 'Paciente' }}</td>
                  <td>{{ optional($nota->cita->doctor)->name ?? 'Doctor/a' }}</td>
                  <td>{{ optional($nota->cita->especialidad)->nombre ?? '-' }}</td>
                  <td>
                    {{ $nota->cita->fecha ? \Carbon\Carbon::parse($nota->cita->fecha)->format('d/m/Y') : '-' }}
                    {{ $nota->cita->hora ? \Carbon\Carbon::parse($nota->cita->hora)->format('H:i') : '' }}
                  </td>
                  <td class="text-right">
                    <a href="{{ route('admin.historial.show', $nota->id) }}" class="btn btn-outline">
                      <i class="ri-file-list-2-line"></i> Ver
                    </a>
                    @if($nota->cita->paciente_id)
                      <a href="{{ route('admin.historial.paciente', $nota->cita->paciente_id) }}" class="btn btn-ghost">Paciente</a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>

      @if(method_exists($notas, 'links'))
        <div class="flex justify-center">
          {{ $notas->links() }}
        </div>
      @endif
    @endif
  </div>
@endsection

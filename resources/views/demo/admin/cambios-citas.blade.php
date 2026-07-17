@extends('layouts.demo')
@section('title','Cambios de citas | Administración - Demo')
@section('header-title','Cambios de citas')
@section('header-subtitle','Auditoría y trazabilidad (Demo)')

@section('sidebar')
    @include('demo.partials.sidebar-admin-demo')
@endsection

@php
  $mockChanges = [
      [
          'created_at' => now()->subMinutes(15),
          'tipo' => 'prioridad_manual',
          'cita_id' => 10,
          'paciente' => 'Lucia Vega',
          'doctor' => 'Dra. Sofia Cardenas',
          'de_estado' => 'pendiente',
          'a_estado' => 'pendiente',
          'valor_anterior' => 'BAJA',
          'valor_nuevo' => 'ALTA',
          'comentario' => 'Caso urgente derivado por pediatra',
      ],
      [
          'created_at' => now()->subHour(),
          'tipo' => 'reprogramada',
          'cita_id' => 11,
          'paciente' => 'Daniel Salazar',
          'doctor' => 'Dr. Andres Molina',
          'de_fecha' => now()->toDateString(),
          'de_hora' => '11:00',
          'a_fecha' => now()->addDay()->toDateString(),
          'a_hora' => '12:00',
          'comentario' => 'Paciente solicita cambio por motivos laborales',
      ]
  ];

  $evCollection = collect($mockChanges)->map(function($c, $index) {
      $r = new \stdClass();
      $r->id = $index + 1;
      $r->created_at = $c['created_at'];
      $r->tipo = $c['tipo'];
      $r->cita_id = $c['cita_id'];
      
      $cita = new \App\Models\Cita();
      $cita->id = $c['cita_id'];
      $cita->setRelation('paciente', new \App\Models\User(['name' => $c['paciente']]));
      $cita->setRelation('doctor', new \App\Models\User(['name' => $c['doctor']]));
      
      $r->cita = $cita;
      $r->de_estado = $c['de_estado'] ?? null;
      $r->a_estado = $c['a_estado'] ?? null;
      $r->de_fecha = $c['de_fecha'] ?? null;
      $r->de_hora = $c['de_hora'] ?? null;
      $r->a_fecha = $c['a_fecha'] ?? null;
      $r->a_hora = $c['a_hora'] ?? null;
      $r->valor_anterior = $c['valor_anterior'] ?? null;
      $r->valor_nuevo = $c['valor_nuevo'] ?? null;
      $r->comentario = $c['comentario'] ?? null;
      return $r;
  });

  $ev = new \Illuminate\Pagination\LengthAwarePaginator($evCollection, $evCollection->count(), 15, 1, [
      'path' => request()->url(),
      'query' => request()->query(),
  ]);

  $doctoresList = collect($usuarios)->filter(fn($u) => strtolower($u['role']) === 'doctor')->map(function($u) {
      $userObj = new \App\Models\User($u);
      $userObj->id = $u['id'];
      return $userObj;
  });

  $eventLabels = [
    'agendada' => 'Agendada',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'realizada' => 'Realizada',
    'no_se_presento' => 'No se presentó',
    'reprogramada' => 'Reprogramada',
    'prioridad_manual' => 'Prioridad manual',
  ];
  $stateLabels = [
    'pendiente' => 'Pendiente',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
    'realizada' => 'Realizada',
    'no_se_presento' => 'No se presentó',
  ];
@endphp

@section('main')
<div class="space-y-6">
  <div class="panel-action-bar">
    <button type="button" class="btn btn-outline shrink-0 demo-action-blocked">
      <i class="ri-filter-3-line"></i> Mostrar filtros
    </button>
  </div>

  <div class="grid gap-6 xl:grid-cols-1">
    <div class="card min-w-0 overflow-visible p-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-gray-500">Resultados</p>
          <h3 class="mt-2 text-lg font-semibold text-gray-900">Eventos encontrados</h3>
          <span class="text-sm text-gray-500">{{ $ev->total() }} eventos (Simulado)</span>
        </div>
        <div class="relative">
          <button type="button" class="btn btn-outline btn-sm demo-action-blocked">
            <i class="ri-more-2-fill"></i> Exportar
          </button>
        </div>
      </div>

      @if($ev->count())
        <div class="mt-4 table-shell table-responsive-cards overflow-x-auto">
          <table class="table w-full min-w-[980px] xl:min-w-[900px] 2xl:min-w-full">
            <thead>
              <tr>
                <th>Fecha/Hora</th>
                <th>Evento</th>
                <th>Cita</th>
                <th>Paciente</th>
                <th>Doctor</th>
                <th class="text-center">De</th>
                <th class="text-center">A</th>
                <th>Detalle</th>
              </tr>
            </thead>
            <tbody>
            @foreach($ev as $registro)
              @php
                $pillMap = ['agendada'=>'info','confirmada'=>'success','cancelada'=>'danger','realizada'=>'success','no_se_presento'=>'danger','reprogramada'=>'warning','pendiente'=>'warning','prioridad_manual'=>'warning'];
                $pillClass = $pillMap[$registro->tipo] ?? 'neutral';
                $iconMap = ['agendada'=>'ri-calendar-event-line','confirmada'=>'ri-check-line','cancelada'=>'ri-close-line','realizada'=>'ri-check-double-line','no_se_presento'=>'ri-close-circle-line','reprogramada'=>'ri-swap-line','prioridad_manual'=>'ri-flag-2-line'];
                $icon = $iconMap[$registro->tipo] ?? 'ri-history-line';
                $fromStateLabel = $stateLabels[$registro->de_estado] ?? ($registro->de_estado ?: '-');
                $toStateLabel = $stateLabels[$registro->a_estado] ?? ($registro->a_estado ?: '-');
              @endphp
              <tr>
                <td data-label="Fecha / hora" class="whitespace-nowrap">
                  <div>{{ $registro->created_at->format('d/m/Y') }}</div>
                  <div class="text-xs text-gray-500">{{ $registro->created_at->format('H:i') }}</div>
                </td>
                <td data-label="Evento">
                  <span class="badge {{ $pillClass }} whitespace-nowrap"><i class="{{ $icon }}"></i> {{ $eventLabels[$registro->tipo] ?? ucfirst($registro->tipo) }}</span>
                </td>
                <td data-label="Cita" class="whitespace-nowrap">#{{ $registro->cita_id }}</td>
                <td data-label="Paciente" class="max-w-[11rem] break-words">{{ optional($registro->cita->paciente)->name ?? '-' }}</td>
                <td data-label="Doctor" class="max-w-[12rem] break-words">{{ optional($registro->cita->doctor)->name ?? '-' }}</td>
                <td data-label="De">
                  @if($registro->tipo === 'reprogramada' && !blank($registro->de_fecha))
                    <div class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($registro->de_fecha)->format('d/m/Y') }}</div>
                    <div class="text-xs text-gray-500">{{ $registro->de_hora }}</div>
                  @else
                    <span class="badge neutral whitespace-nowrap">{{ $fromStateLabel }}</span>
                  @endif
                </td>
                <td data-label="A">
                  @if($registro->tipo === 'reprogramada' && !blank($registro->a_fecha))
                    <div class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($registro->a_fecha)->format('d/m/Y') }}</div>
                    <div class="text-xs text-gray-500">{{ $registro->a_hora }}</div>
                  @else
                    <span class="badge neutral whitespace-nowrap">{{ $toStateLabel }}</span>
                  @endif
                </td>
                <td data-label="Detalle" class="max-w-[18rem] break-words text-sm text-gray-600">
                  @if($registro->tipo === 'prioridad_manual')
                    <div><strong>{{ $registro->valor_anterior ?? '-' }}</strong></div>
                    <div class="mt-1 text-xs text-gray-500">-> {{ $registro->valor_nuevo ?? '-' }}</div>
                    @if(!blank($registro->comentario))
                      <div class="mt-1 text-xs text-gray-500">{{ $registro->comentario }}</div>
                    @endif
                  @elseif(!blank($registro->comentario))
                    <span class="text-xs text-gray-500">{{ $registro->comentario }}</span>
                  @else
                    <span class="text-xs text-gray-400">Sin comentario adicional.</span>
                  @endif
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="mt-4">
          <x-ui.empty-state title="No hay eventos con estos filtros." message="Ajusta el rango, cambia el evento o limpia los filtros para revisar otros movimientos de agenda." />
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

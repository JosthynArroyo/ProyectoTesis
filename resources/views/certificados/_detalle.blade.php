@php
  $cita = $certificado->cita;
  $paciente = $certificado->paciente;
  $doctor = $certificado->doctor;
  $especialidad = $cita?->especialidad?->nombre
      ?? $doctor?->especialidades?->first()?->nombre
      ?? 'Especialidad no registrada';

  $nombreReal = $cita ? $cita->nombrePacienteReal() : ($certificado->dependiente_id && $certificado->dependiente ? $certificado->dependiente->nombre : ($paciente?->name ?? '-'));
  $dniReal = $cita ? $cita->dniPacienteReal() : ($certificado->dependiente_id && $certificado->dependiente ? $certificado->dependiente->dni : ($paciente?->dni ?? 'Sin registro'));
@endphp

<section class="card p-6">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <p class="text-xs uppercase tracking-widest text-gray-500">Certificado medico</p>
      <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $certificado->codigo }}</h1>
      <p class="mt-1 text-sm text-gray-600">
        Emitido el {{ $certificado->fecha_emision?->format('d/m/Y H:i') ?? '-' }}
      </p>
    </div>
    <span class="badge success">Documento emitido</span>
  </div>

  <div class="mt-6 grid gap-4 md:grid-cols-2">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
      <p class="text-xs uppercase tracking-widest text-gray-500">Paciente</p>
      <p class="mt-2 font-semibold text-gray-900">{{ $nombreReal }}</p>
      <p class="text-sm text-gray-600">Documento: {{ $dniReal }}</p>
      <p class="text-sm text-gray-600">Correo: {{ $paciente?->email ?: 'Sin registro' }}</p>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
      <p class="text-xs uppercase tracking-widest text-gray-500">Doctor emisor</p>
      <p class="mt-2 font-semibold text-gray-900">{{ $doctor?->name ?? '-' }}</p>
      <p class="text-sm text-gray-600">Especialidad: {{ $especialidad }}</p>
      <p class="text-sm text-gray-600">Usuario sistema: #{{ $doctor?->id ?? '-' }}</p>
    </div>
  </div>

  <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4">
    <p class="text-xs uppercase tracking-widest text-gray-500">Cita relacionada</p>
    <p class="mt-2 text-sm text-gray-700">
      Cita #{{ $cita?->id ?? '-' }}
      @if($cita?->fecha)
        | {{ $cita->fecha->format('d/m/Y') }}
      @endif
      @if($cita?->hora)
        {{ substr((string) $cita->hora, 0, 5) }}
      @endif
      | Estado: {{ $cita?->estado ?? '-' }}
    </p>
  </div>

  <div class="mt-6 rounded-lg border border-gray-200 bg-gray-100/60 p-5 constancia-medica-box">
    <p class="text-xs uppercase tracking-widest text-gray-700 constancia-title">Constancia medica</p>
    <div class="mt-3 whitespace-pre-line text-sm leading-7 text-gray-800 constancia-text">{{ $certificado->texto_constancia }}</div>
  </div>

  <div class="mt-4 grid gap-4 md:grid-cols-3">
    <div class="rounded-lg border border-gray-200 p-4">
      <p class="text-xs uppercase tracking-widest text-gray-500">Dias de reposo</p>
      <p class="mt-2 text-lg font-semibold text-gray-900">{{ $certificado->dias_reposo }}</p>
    </div>
    <div class="rounded-lg border border-gray-200 p-4">
      <p class="text-xs uppercase tracking-widest text-gray-500">Desde</p>
      <p class="mt-2 font-semibold text-gray-900">{{ $certificado->reposo_desde?->format('d/m/Y') ?? 'No aplica' }}</p>
    </div>
    <div class="rounded-lg border border-gray-200 p-4">
      <p class="text-xs uppercase tracking-widest text-gray-500">Hasta</p>
      <p class="mt-2 font-semibold text-gray-900">{{ $certificado->reposo_hasta?->format('d/m/Y') ?? 'No aplica' }}</p>
    </div>
  </div>

  @if($certificado->observaciones)
    <div class="mt-4 rounded-lg border border-gray-200 p-4">
      <p class="text-xs uppercase tracking-widest text-gray-500">Observaciones y recomendaciones</p>
      <div class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $certificado->observaciones }}</div>
    </div>
  @endif

  <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
    <p class="font-semibold text-gray-900">Validacion interna</p>
    <p>Emitido por {{ $doctor?->name ?? 'doctor registrado' }} desde el sistema, usuario #{{ $doctor?->id ?? '-' }}, codigo {{ $certificado->codigo }}.</p>
    <p class="mt-1">
      Envio:
      <strong>{{ strtoupper((string) ($certificado->envio_estado ?? 'pendiente')) }}</strong>
      @if($certificado->enviado_a)
        | Destinatario: {{ $certificado->enviado_a }}
      @endif
      @if($certificado->enviado_en)
        | Enviado: {{ $certificado->enviado_en->format('d/m/Y H:i') }}
      @endif
    </p>
    @if($certificado->envio_error)
      <p class="mt-1 text-rose-600">Ultimo error: {{ $certificado->envio_error }}</p>
    @endif
  </div>
</section>

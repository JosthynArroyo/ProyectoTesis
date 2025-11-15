@extends('layouts.admin')
@section('title','Usuario')

@push('head')
  @vite('resources/css/admin/users/show.css')
@endpush

@section('main')
<div class="wrap">
  <header class="header">
    <div class="actions-left">
      <a class="btn btn-secondary" href="{{ route('admin.usuarios.index') }}">
        <span class="material-symbols-outlined">arrow_back</span>
        Volver
      </a>
    </div>
    <div class="title">
      <h2>Detalle usuario #{{ $user->id }}</h2>
      <p class="subtitle">{{ optional($user->roles->first())->name ?? '—' }}</p>
    </div>
    <div class="actions-right">
      <a class="btn btn-primary" href="{{ route('admin.usuarios.edit',$user) }}">
        <span class="material-symbols-outlined">edit</span>
        Editar
      </a>
    </div>
  </header>

  <section class="card">
    <div class="profile">
      <div class="avatar">
        <img src="{{ $user->avatar ? asset('storage/'.$user->avatar) : asset('img/doctor1.jpg') }}" alt="Avatar">
      </div>
      <div class="identity">
        <h3>{{ $user->name }}</h3>
        <div class="pill">{{ $user->status ?? 'active' }}</div>
        @if($user->suspended_until)
          <div class="muted">Suspendido hasta {{ $user->suspended_until->format('Y-m-d H:i') }}</div>
        @endif
      </div>
    </div>

    <dl class="grid">
      <div class="item">
        <dt>Email</dt>
        <dd>
          <span id="emailText">{{ $user->email }}</span>
          <button class="icon-btn" data-copy="#emailText"><span class="material-symbols-outlined">content_copy</span></button>
        </dd>
      </div>

      <div class="item">
        <dt>Teléfono</dt>
        <dd>
          <span id="telText">{{ $user->telefono ?? '—' }}</span>
          @if($user->telefono)
            <a class="icon-btn" href="tel:{{ $user->telefono }}"><span class="material-symbols-outlined">call</span></a>
            <button class="icon-btn" data-copy="#telText"><span class="material-symbols-outlined">content_copy</span></button>
          @endif
        </dd>
      </div>

      <div class="item"><dt>Cédula</dt><dd>{{ $user->dni ?? '—' }}</dd></div>
      <div class="item full"><dt>Dirección</dt><dd>{{ $user->direccion ?? '—' }}</dd></div>
      <div class="item"><dt>Fecha de nacimiento</dt><dd>{{ $user->fecha_nacimiento?->format('Y-m-d') ?? '—' }}</dd></div>
      <div class="item"><dt>Sexo</dt><dd>{{ $user->sexo ?? '—' }}</dd></div>
      <div class="item full"><dt>Especialidades</dt><dd>{{ ($user->especialidades?->pluck('nombre')->implode(', ')) ?: '—' }}</dd></div>
      <div class="item"><dt>Último acceso</dt><dd>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
      <div class="item"><dt>Creado</dt><dd>{{ $user->created_at?->format('Y-m-d H:i') }}</dd></div>
    </dl>
  </section>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/users/show.js')
@endpush

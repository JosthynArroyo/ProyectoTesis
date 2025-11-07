@extends('layouts.admin')
@section('title','Editar usuario')

@push('head')
  @vite('resources/css/admin/user-edit.css')
@endpush

@section('main')
<div class="page">
  <h1 class="h1">Editar usuario #{{ $user->id }}</h1>
  <p class="subtitle">Modifica datos personales, contacto y rol.</p>

  @if (session('success'))
    <div class="alert-ok">✅ {{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert-err">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
  @endif

  <form class="card main-form" method="POST" action="{{ route('admin.usuarios.update',$user) }}">
    @csrf @method('PUT')
    {{-- Tu parcial, SIN cambios --}}
    @include('admin.users.form', ['user'=>$user, 'roles'=>$roles, 'especialidades'=>$especialidades ?? collect()])

    <div class="actions">
      <a class="btn ghost" href="{{ route('admin.usuarios.index') }}">
        <span class="material-symbols-outlined">arrow_back</span> Volver
      </a>
      <button class="btn" type="submit">
        <span class="material-symbols-outlined">save</span> Guardar
      </button>
    </div>
  </form>

  <aside class="side box">
    <h3>Resumen</h3>
    <ul class="kv">
      <li><span>ID</span><strong>#{{ $user->id }}</strong></li>
      <li><span>Creado</span><strong>{{ $user->created_at?->format('Y-m-d H:i') ?? '—' }}</strong></li>
      <li><span>Último acceso</span><strong>{{ $user->last_login_at?->diffForHumans() ?? '—' }}</strong></li>
      <li><span>Estado</span><strong>{{ ucfirst($user->status ?? 'active') }}</strong></li>
    </ul>
  </aside>

  <aside class="side box">
    <h3>Edad</h3>
    <div id="age-badge">—</div>
    <small class="muted">Según fecha de nacimiento.</small>
  </aside>
</div>
@endsection

@push('scripts')
  @vite('resources/js/admin/user-edit.js')
@endpush

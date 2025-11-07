{{-- resources/views/admin/users/show.blade.php --}}
@extends('layouts.admin')
@section('title','Usuario')
@push('head')
<style>.wrap{max-width:880px;margin:96px auto 40px;padding:0 24px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:20px;box-shadow:0 18px 34px rgba(15,23,42,.08);padding:22px}dt{font-weight:800;color:#0f172a}dd{margin:0 0 10px 0}</style>
@endpush
@section('main')
<div class="wrap">
  <h2 style="margin:0 0 16px 0;font-weight:800">Detalle usuario #{{ $user->id }}</h2>
  <div class="card">
    <dl>
      <dt>Nombre</dt><dd>{{ $user->name }}</dd>
      <dt>Email</dt><dd>{{ $user->email }}</dd>
      <dt>Teléfono</dt><dd>{{ $user->telefono ?? '—' }}</dd>
      <dt>Cédula</dt><dd>{{ $user->dni ?? '—' }}</dd>
      <dt>Dirección</dt><dd>{{ $user->direccion ?? '—' }}</dd>
      <dt>Fecha de nacimiento</dt><dd>{{ $user->fecha_nacimiento?->format('Y-m-d') ?? '—' }}</dd>
      <dt>Sexo</dt><dd>{{ $user->sexo ?? '—' }}</dd>
      <dt>Rol</dt><dd>{{ optional($user->roles->first())->name ?? '—' }}</dd>
      <dt>Especialidades</dt><dd>{{ ($user->especialidades?->pluck('nombre')->implode(', ')) ?: '—' }}</dd>
      <dt>Estado</dt><dd>{{ $user->status ?? 'active' }} @if($user->suspended_until) (suspendido hasta {{ $user->suspended_until->format('Y-m-d H:i') }}) @endif</dd>
      <dt>Último acceso</dt><dd>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd>
      <dt>Creado</dt><dd>{{ $user->created_at?->format('Y-m-d H:i') }}</dd>
    </dl>
    <div style="display:flex;gap:12px;margin-top:12px">
      <a class="btn" href="{{ route('admin.usuarios.edit',$user) }}" style="background:#1d4ed8;color:#fff;display:inline-flex;align-items:center;gap:.4rem;border-radius:12px;padding:.7rem 1rem;font-weight:700"><span class="material-symbols-outlined">edit</span> Editar</a>
      <a class="btn" href="{{ route('admin.usuarios.index') }}" style="background:#64748b;color:#fff;display:inline-flex;align-items:center;gap:.4rem;border-radius:12px;padding:.7rem 1rem;font-weight:700">Volver</a>
    </div>
  </div>
</div>
@endsection

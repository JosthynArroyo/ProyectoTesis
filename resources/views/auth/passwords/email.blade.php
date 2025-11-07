{{-- resources/views/auth/passwords/email.blade.php --}}
@extends('layouts.app')
@section('title','Recuperar acceso')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet">
<style>
:root{
  --bg:#f7f8fa; --card:#fff; --ink:#0f172a; --muted:#667085; --line:#e6e7eb;
  --brand:#2563eb; --success-bg:#ecfdf5; --success-bd:#bbf7d0; --success-tx:#065f46;
  --danger:#b42318; --radius:16px; --shadow:0 10px 24px rgba(2,6,23,.08);
}
html,body{background:var(--bg); font-family:"Plus Jakarta Sans",sans-serif}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 500,'GRAD' 0,'opsz' 24}
.auth{min-height:calc(100dvh - 80px); display:grid; place-items:center; padding:28px}
.card{width:100%; max-width:640px; background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow)}
.head{padding:22px 24px; border-bottom:1px solid var(--line)}
.title{margin:0; font-size:20px; font-weight:700; color:var(--ink)}
.sub{margin-top:6px; font-size:14px; color:var(--muted)}
.body{padding:24px}
.alert{display:flex; gap:10px; align-items:center; padding:12px 14px; border-radius:12px; background:var(--success-bg); color:var(--success-tx); border:1px solid var(--success-bd); margin-bottom:16px}
.field{margin-bottom:18px}
.label{display:block; font-weight:600; color:var(--ink); font-size:14px; margin-bottom:8px}
.ctrl{display:flex; align-items:center; border:1px solid var(--line); background:#fff; border-radius:12px; transition:box-shadow .15s}
.ctrl:focus-within{box-shadow:0 0 0 4px rgba(37,99,235,.15)}
.input{width:100%; border:0; outline:0; background:transparent; padding:12px 14px; font-size:15px; color:var(--ink)}
.hint{font-size:12px; color:var(--muted); margin-top:6px}
.error{font-size:12px; color:var(--danger); margin-top:6px}
.actions{display:flex; justify-content:flex-end; margin-top:8px}
.btn{
  border:0; border-radius:12px; padding:12px 18px;
  font-weight:700; font-size:14px; cursor:pointer;
  background:var(--brand); color:#fff; transition:.15s;
  display:inline-flex; align-items:center; gap:8px;
}
.btn:hover{filter:brightness(0.95)}
</style>
@endpush

@section('content')
<div class="auth">
  <div class="card">
    <div class="head">
      <h1 class="title">Recuperar acceso</h1>
      <p class="sub">Ingresa tu correo para enviarte un enlace de restablecimiento.</p>
    </div>

    <div class="body">
      @if (session('status'))
        <div class="alert" role="status">
          <span class="material-symbols-outlined">check_circle</span>
          <span>{{ session('status') }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
          <label for="email" class="label">Correo electrónico</label>
          <div class="ctrl">
            <input id="email" type="email" class="input @error('email') is-invalid @enderror"
                   name="email" value="{{ old('email') }}" required placeholder="tucorreo@ejemplo.com">
          </div>
          @error('email') <div class="error">{{ $message }}</div>
          @else <div class="hint">Debe ser un correo registrado en el sistema.</div> @enderror
        </div>

        <div class="actions">
          <button type="submit" class="btn">
            <span class="material-symbols-outlined">send</span> Enviar enlace
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

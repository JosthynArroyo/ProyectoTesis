{{-- resources/views/auth/passwords/reset.blade.php --}}
@extends('layouts.app')
@section('title','Restablecer contraseña')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Material+Symbols+Outlined" rel="stylesheet">
<style>
:root{
  --bg:#f7f8fa; --card:#fff; --ink:#0f172a; --muted:#667085; --line:#e6e7eb;
  --brand:#2563eb; --danger:#b42318;
  --radius:16px; --shadow:0 10px 24px rgba(2,6,23,.08);
}
html,body{background:var(--bg); font-family:"Plus Jakarta Sans",sans-serif}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 500,'GRAD' 0,'opsz' 24}
.auth{min-height:calc(100dvh - 80px); display:grid; place-items:center; padding:28px}
.card{width:100%; max-width:640px; background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow)}
.head{padding:22px 24px; border-bottom:1px solid var(--line)}
.title{margin:0; font-size:20px; font-weight:700; color:var(--ink)}
.sub{margin-top:6px; font-size:14px; color:var(--muted)}
.body{padding:24px}
.field{margin-bottom:18px}
.label{display:block; font-weight:600; color:var(--ink); font-size:14px; margin-bottom:8px}
.ctrl{display:flex; align-items:center; border:1px solid var(--line); background:#fff; border-radius:12px; transition:box-shadow .15s; position:relative}
.ctrl:focus-within{box-shadow:0 0 0 4px rgba(37,99,235,.15)}
.input{width:100%; border:0; outline:0; background:transparent; padding:12px 14px; font-size:15px; color:var(--ink)}
.addon{position:absolute; right:6px; top:50%; transform:translateY(-50%)}
.icon-btn{border:0; background:transparent; cursor:pointer; padding:6px; border-radius:8px; color:#334155}
.icon-btn:hover{background:#f2f4f7}
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
      <h1 class="title">Restablecer contraseña</h1>
      <p class="sub">Define tu nueva contraseña.</p>
    </div>

    <div class="body">
      <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
          <label for="email" class="label">Correo electrónico</label>
          <div class="ctrl">
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" class="input @error('email') is-invalid @enderror" required placeholder="tucorreo@ejemplo.com">
          </div>
          @error('email') <div class="error">{{ $message }}</div>
          @else <div class="hint">Usa el mismo correo con el que solicitaste el enlace.</div> @enderror
        </div>

        <div class="field">
          <label for="password" class="label">Nueva contraseña</label>
          <div class="ctrl">
            <input id="password" type="password" name="password" class="input @error('password') is-invalid @enderror" required placeholder="••••••••">
            <div class="addon">
              <button type="button" class="icon-btn" data-toggle="pw" data-target="#password">
                <span class="material-symbols-outlined">visibility</span>
              </button>
            </div>
          </div>
          @error('password') <div class="error">{{ $message }}</div>
          @else <div class="hint">Mínimo 8 caracteres. Combina letras y números.</div> @enderror
        </div>

        <div class="field">
          <label for="password-confirm" class="label">Confirmar nueva contraseña</label>
          <div class="ctrl">
            <input id="password-confirm" type="password" name="password_confirmation" class="input" required placeholder="••••••••">
            <div class="addon">
              <button type="button" class="icon-btn" data-toggle="pw" data-target="#password-confirm">
                <span class="material-symbols-outlined">visibility</span>
              </button>
            </div>
          </div>
          <div class="hint">Debe coincidir con la nueva contraseña.</div>
        </div>

        <div class="actions">
          <button type="submit" class="btn">
            <span class="material-symbols-outlined">done</span> Restablecer
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click',(e)=>{
  const b=e.target.closest('[data-toggle="pw"]'); if(!b) return;
  const i=document.querySelector(b.getAttribute('data-target')); if(!i) return;
  const icon=b.querySelector('.material-symbols-outlined');
  if(i.type==='password'){ i.type='text'; icon.textContent='visibility_off'; }
  else{ i.type='password'; icon.textContent='visibility'; }
});
</script>
@endpush

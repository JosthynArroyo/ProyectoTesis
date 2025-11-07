@extends('layouts.admin')
@section('title','Perfil del Administrador')
@push('head')
<style>
  :root{
    --perfil-admin-primary:#1d4ed8;
    --perfil-admin-muted:#64748b;
    --perfil-admin-dark:#0f172a;
    --perfil-admin-border:#e2e8f0;
  }
  body{background:#f5f7fb;}
  .profile-page{width:min(1080px,100%);margin:90px auto 60px;padding:0 24px 60px;display:grid;gap:24px;}
  .profile-hero{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1.4rem;padding:26px 32px;border-radius:28px;background:#ffffff;border:1px solid var(--perfil-admin-border);box-shadow:0 24px 42px rgba(17,24,39,.08);animation:fadeSlideUp .55s ease both;}
  .profile-hero h1{margin:0;font-size:2.15rem;font-weight:800;color:var(--perfil-admin-dark);} 
  .profile-hero p{margin:.6rem 0 0;color:var(--perfil-admin-muted);font-weight:500;max-width:44ch;} 
  .profile-hero .badge{display:inline-flex;align-items:center;gap:.55rem;padding:.6rem 1.1rem;border-radius:999px;font-weight:700;background:rgba(29,78,216,.08);border:1px solid var(--perfil-admin-border);color:var(--perfil-admin-primary);animation:fadeIn .6s ease both;}
  .profile-hero .badge .material-symbols-outlined{font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 24;color:var(--perfil-admin-primary);}
  .profile-layout{display:grid;grid-template-columns:320px 1fr;gap:24px;align-items:start;}
  .profile-sidebar{background:#fff;border-radius:24px;border:1px solid var(--perfil-admin-border);box-shadow:0 22px 38px rgba(17,24,39,.08);padding:28px;display:grid;gap:22px;animation:fadeSlideUp .6s ease both;}
  .avatar{position:relative;width:140px;height:140px;border-radius:50%;overflow:hidden;border:4px solid #eff4ff;box-shadow:0 14px 22px rgba(17,24,39,.12);margin:0 auto;}
  .avatar img{width:100%;height:100%;object-fit:cover;display:block;}
  .overlay{position:absolute;left:0;right:0;bottom:0;height:48px;background:linear-gradient(180deg,transparent,rgba(0,0,0,.7));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.9rem;opacity:0;transition:opacity .2s;cursor:pointer;}
  .avatar:hover .overlay{opacity:1;}
  .profile-sidebar h2{text-align:center;margin:0;font-size:1.3rem;font-weight:800;color:var(--perfil-admin-dark);} 
  .profile-sidebar span{display:block;text-align:center;color:var(--perfil-admin-muted);font-weight:600;}
  .profile-sidebar .summary{display:grid;gap:.45rem;}
  .profile-sidebar .summary-item{display:flex;align-items:center;gap:.6rem;padding:.55rem .75rem;border-radius:14px;background:rgba(29,78,216,.08);color:var(--perfil-admin-dark);font-weight:600;font-size:.92rem;animation:fadeIn .6s ease both;}
  .profile-sidebar .summary-item:nth-child(2){animation-delay:.1s;}
  .profile-sidebar .summary-item:nth-child(3){animation-delay:.2s;}
  .profile-sidebar .summary-item .material-symbols-outlined{color:var(--perfil-admin-primary);font-variation-settings:'FILL' 1,'wght' 500,'GRAD' 0,'opsz' 24;}
  .profile-card{background:#fff;border-radius:24px;border:1px solid var(--perfil-admin-border);box-shadow:0 26px 42px rgba(17,24,39,.08);overflow:hidden;animation:fadeSlideUp .6s ease both;}
  .profile-card__header{padding:1.6rem 2.2rem;background:#ffffff;border-bottom:1px solid var(--perfil-admin-border);}
  .profile-card__header h3{margin:0;font-size:1.4rem;font-weight:800;color:var(--perfil-admin-dark);} 
  .profile-card__header span{color:var(--perfil-admin-muted);font-size:.95rem;}
  .profile-card__body{padding:2.2rem;display:grid;gap:2rem;}
  .section{border:1px dashed var(--perfil-admin-border);border-radius:20px;padding:1.6rem;background:#f8faff;animation:fadeSlideUp .65s ease both;}
  .section-title{margin:0 0 1.1rem;font-size:1.05rem;font-weight:700;color:var(--perfil-admin-dark);display:flex;align-items:center;gap:.6rem;}
  .section-title .material-symbols-outlined{color:var(--perfil-admin-primary);font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 24;}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.2rem 1.4rem;}
  label{display:block;margin-bottom:.45rem;font-weight:700;color:var(--perfil-admin-dark);}
  .input,.select{width:100%;height:50px;border-radius:14px;border:1.5px solid var(--perfil-admin-border);background:#ffffff;padding:0 1.1rem;font-weight:600;color:var(--perfil-admin-dark);transition:border .15s,box-shadow .15s;}
  .input:focus,.select:focus{outline:none;border-color:var(--perfil-admin-primary);box-shadow:0 0 0 6px rgba(29,78,216,.16);}
  .field-help{font-size:.82rem;color:var(--perfil-admin-muted);margin-top:.35rem;}
  .alerts{display:grid;gap:1rem;margin:0 2.2rem;}
  .alert{padding:1rem 1.2rem;border-radius:18px;font-weight:600;border:1px solid transparent;}
  .alert.success{background:#ecfdf5;border-color:#bbf7d0;color:#047857;}
  .alert.danger{background:#fff0f2;border-color:#fecdd3;color:#9f1239;}
  .profile-actions{display:flex;justify-content:flex-end;gap:.75rem;padding:0 2.2rem 2.2rem;flex-wrap:wrap;}
  .btn{border:none;border-radius:14px;padding:.85rem 1.4rem;font-weight:800;cursor:pointer;font-size:.95rem;transition:transform .05s,filter .2s,box-shadow .2s;}
  .btn:active{transform:translateY(1px) scale(.995);} 
  .btn-secondary{background:#f8faff;border:1px solid var(--perfil-admin-border);color:var(--perfil-admin-dark);}
  .btn-primary{background:var(--perfil-admin-primary);color:#fff;box-shadow:0 20px 32px rgba(29,78,216,.22);}
  .btn-primary:hover{filter:brightness(.95);}
  @media (max-width:980px){
    .profile-layout{grid-template-columns:1fr;}
    .profile-sidebar{order:2;}
  }
</style>
@endpush
@section('main')
  <div class="profile-page">
    <section class="profile-hero">
      <div>
        <h1>Perfil del administrador</h1>
        <p>Actualiza tus datos para mantener la comunicación y los accesos del equipo siempre al día.</p>
      </div>
      <span class="badge"><span class="material-symbols-outlined">verified_user</span>Cuenta protegida</span>
    </section>
    <div class="profile-layout">
      <aside class="profile-sidebar">
        <div class="avatar">
          <img id="avatarPreview" src="{{ $user->avatar ? asset('storage/'.$user->avatar) : asset('img/doctor1.jpg') }}" alt="Avatar">
          <div class="overlay" id="changePhoto">Cambiar foto</div>
        </div>
        <div>
          <h2>{{ $user->name }}</h2>
          <span>{{ $user->email }}</span>
        </div>
        <div class="summary">
          <div class="summary-item"><span class="material-symbols-outlined">shield_person</span> Rol: Administrador</div>
          @if($user->telefono)
            <div class="summary-item"><span class="material-symbols-outlined">call</span> {{ $user->telefono }}</div>
          @endif
          @if($user->direccion)
            <div class="summary-item"><span class="material-symbols-outlined">location_on</span> {{ $user->direccion }}</div>
          @endif
        </div>
      </aside>

      <section class="profile-card">
        <div class="profile-card__header">
          <h3>Información personal</h3>
          <span>Los cambios se aplican inmediatamente después de guardar.</span>
        </div>

        @if(session('success'))
          <div class="alerts"><div class="alert success">{{ session('success') }}</div></div>
        @endif
        @if ($errors->any())
          <div class="alerts">
            <div class="alert danger">
              <ul style="margin:0 0 0 18px;padding:0;">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          </div>
        @endif

        <form method="POST" action="{{ route('admin.perfil.update') }}" enctype="multipart/form-data">
          @csrf
          <input id="avatarInput" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp" hidden>

          <div class="profile-card__body">
            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">badge</span>Datos principales</h4>
              <div class="grid">
                <div>
                  <label>Nombre completo</label>
                  <input class="input" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                </div>
                <div>
                  <label>Correo electrónico</label>
                  <input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div>
                  <label>Teléfono</label>
                  <input class="input" type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" placeholder="0998740927">
                  <div class="field-help">Formato: 10 dígitos.</div>
                </div>
                <div>
                  <label>Cédula</label>
                  <input class="input" type="text" name="dni" value="{{ old('dni', $user->dni) }}" inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10" placeholder="1723456789">
                  <div class="field-help">Exactamente 10 dígitos.</div>
                </div>
              </div>
            </section>

            <section class="section">
              <h4 class="section-title"><span class="material-symbols-outlined">home_pin</span>Información adicional</h4>
              <div class="grid">
                <div>
                  <label>Dirección</label>
                  <input class="input" type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}">
                </div>
                <div>
                  <label>Fecha de nacimiento</label>
                  <input class="input" type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($user->fecha_nacimiento)->toDateString()) }}">
                </div>
                <div>
                  <label>Sexo</label>
                  <select class="select" name="sexo">
                    <option value="">Seleccionar</option>
                    <option value="Masculino" {{ old('sexo', $user->sexo)=='Masculino'?'selected':'' }}>Masculino</option>
                    <option value="Femenino"  {{ old('sexo', $user->sexo)=='Femenino'?'selected':'' }}>Femenino</option>
                    <option value="Otro"      {{ old('sexo', $user->sexo)=='Otro'?'selected':'' }}>Otro</option>
                  </select>
                </div>
              </div>
            </section>
          </div>

          <div class="profile-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
          </div>
        </form>
      </section>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  const changePhoto=document.getElementById('changePhoto');
  const input=document.getElementById('avatarInput');
  const preview=document.getElementById('avatarPreview');
  changePhoto.addEventListener('click',()=>input.click());
  input.addEventListener('change',(e)=>{const f=e.target.files?.[0];if(!f)return;preview.src=URL.createObjectURL(f);});
</script>
@endpush

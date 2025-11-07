@extends('layouts.doctor')
@section('title', 'Perfil del Doctor')
@section('activeSidebar', 'perfil')
@section('body-class', 'profile-body')
@push('head')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
@endpush
@push('styles')
    <style>
        body.profile-body {
            background: var(--clr-color-background);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Ubuntu, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif;
            color: var(--clr-dark);
            --surface: var(--clr-white);
            --surface-muted: var(--clr-surface-muted);
            --border: var(--clr-border);
            --accent: var(--clr-primary);
            --accent-strong: var(--clr-primary-variant);
            --accent-soft: rgba(15, 76, 117, 0.12);
            --shadow: var(--box-shadow);
            --shadow-lg: 0 28px 48px rgba(15, 76, 117, 0.14);
            --text-muted: var(--clr-dark-variant);
        }
        .profile-wrapper {
            max-width: 1100px;
            margin: 2.8rem auto 3.2rem;
            padding: 0 24px;
            display: grid;
            gap: 24px;
        }
        .profile-hero {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            padding: 28px 32px;
            border-radius: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            animation: fade-in 0.7s ease both, slide-up 0.7s ease both;
        }
        .profile-hero h1 { margin: 0; font-size: 2.15rem; font-weight: 800; }
        .profile-hero p { margin: .5rem 0 0; max-width: 42ch; color: var(--text-muted); font-weight: 500; }
        .profile-hero .badge{
            display:inline-flex; align-items:center; gap:.5rem;
            padding:.65rem 1.1rem; border-radius:999px;
            background:var(--accent-soft); border:1px solid rgba(15,76,117,.25);
            font-weight:700; color:var(--clr-dark);
            animation: fade-in .75s ease both;
        }
        .profile-hero .badge .material-symbols-outlined{font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 24;color:var(--accent);}

        .profile-layout { display:grid; grid-template-columns:320px 1fr; gap:26px; }

        .profile-aside {
            background: var(--surface);
            border-radius: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 28px;
            display: grid;
            gap: 20px;
            align-content: start;
            animation: fade-in .75s ease both, slide-up .75s ease both;
        }
        .profile-avatar{ position:relative; width:140px; height:140px; border-radius:999px; margin:0 auto; overflow:hidden; border:4px solid rgba(15,76,117,.18); box-shadow:0 14px 28px rgba(15,76,117,.16);}
        .profile-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
        .profile-avatar__overlay{ position:absolute; inset:auto 0 0; height:48px; background:linear-gradient(180deg,transparent,rgba(15,76,117,.82)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.9rem; cursor:pointer; opacity:0; transition:opacity .2s;}
        .profile-avatar:hover .profile-avatar__overlay{ opacity:1; }

        .profile-aside h2{ margin:0; text-align:center; font-size:1.35rem; font-weight:800; }
        .profile-aside span{ text-align:center; color:var(--text-muted); font-weight:600; }
        .profile-aside .chips{ display:flex; flex-wrap:wrap; gap:.35rem; justify-content:center; }
        .chip{ background:var(--accent-soft); border-radius:999px; padding:.2rem .7rem; color:var(--accent-strong); font-weight:700; font-size:.78rem; border:1px solid rgba(15,76,117,.24); }
        .profile-aside .summary{ display:grid; gap:.45rem; }
        .summary-item{ display:flex; align-items:center; gap:.55rem; padding:.55rem .75rem; border-radius:14px; background:var(--accent-soft); color:var(--accent-strong); font-weight:600; font-size:.9rem; border:1px solid rgba(15,76,117,.24); }
        .summary-item .material-symbols-outlined{ color:var(--accent); font-variation-settings:'FILL' 1,'wght' 500,'GRAD' 0,'opsz' 24; }

        .profile-card{
            background: var(--surface);
            border-radius: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: fade-in .8s ease both, slide-up .8s ease both;
        }
        .profile-card__header{ padding:1.6rem 2.2rem; background:var(--surface-muted); border-bottom:1px solid var(--border); }
        .profile-card__header h3{ margin:0; font-size:1.4rem; font-weight:800; }
        .profile-card__header span{ color:var(--text-muted); font-size:.95rem; }

        .profile-card__body{ padding:2.2rem; display:grid; gap:2rem; }

        .section{
            border:1px dashed rgba(15,76,117,.30);
            border-radius:20px;
            padding:1.6rem;
            background:var(--surface);
        }
        .section-title{
            margin:0 0 1.05rem; font-size:1.05rem; font-weight:700;
            display:flex; align-items:center; gap:.6rem; color:var(--accent-strong);
        }
        .section-title .material-symbols-outlined{ color:var(--accent); font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 24; }

        .profile-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1.2rem 1.4rem; }

        /* ====== NUEVO: recuadros para cada campo ====== */
        .field-box{
            border:1px dashed rgba(15,76,117,.28);
            border-radius:16px;
            padding:14px;
            background:var(--surface);
        }

        label{ display:block; font-weight:700; margin-bottom:.45rem; color:var(--clr-dark); }
        input, select{
            width:100%; height:50px; border-radius:14px; border:1.5px solid var(--border);
            background:var(--surface); padding:0 1.1rem; font-weight:600; color:var(--clr-dark);
            transition:border .15s, box-shadow .15s;
        }
        input:focus, select:focus{ outline:none; border-color:var(--accent); box-shadow:0 0 0 4px rgba(15,76,117,.15); }

        .help{ font-size:.82rem; color:var(--text-muted); margin-top:.35rem; }
        .error{ font-size:.85rem; color:var(--clr-danger); margin-top:.35rem; font-weight:600; }

        .alerts{ display:grid; gap:1rem; margin:0 2.2rem; }
        .alert{ padding:1rem 1.2rem; border-radius:18px; font-weight:600; border:1px solid transparent; }
        .alert.success{ background:rgba(26,127,92,.12); border-color:rgba(26,127,92,.32); color:var(--clr-success); }
        .alert.danger{ background:rgba(178,58,72,.12); border-color:rgba(178,58,72,.32); color:var(--clr-danger); }

        .profile-actions{ display:flex; justify-content:flex-end; gap:.75rem; padding:0 2.2rem 2.2rem; flex-wrap:wrap; }
        .btn{
            border-radius:14px; padding:.85rem 1.4rem; font-weight:800; cursor:pointer; font-size:.95rem;
            transition:transform .05s, filter .2s, box-shadow .2s, background .2s, color .2s;
            border:1px solid var(--border); background:var(--surface); color:var(--clr-dark);
            display:inline-flex; align-items:center; justify-content:center; gap:.4rem;
        }
        .btn:active{ transform:translateY(1px) scale(.995); }
        .btn-primary{ color:#fff; background:var(--accent); border-color:var(--accent); box-shadow:0 18px 28px rgba(15,76,117,.22); }
        .btn-primary:hover{ filter:brightness(.95); }
        .btn-secondary{ background:var(--surface-muted); color:var(--accent-strong); border:1px solid var(--border); }

        .hidden{ display:none; }
        @media (max-width:980px){
            .profile-layout{ grid-template-columns:1fr; }
            .profile-aside{ order:2; }
        }
    </style>
@endpush

@section('content')
    <div class="profile-wrapper">
        <section class="profile-hero">
            <div>
                <h1>Perfil del doctor</h1>
                <p>Actualiza tu información profesional y de contacto para mantener informados a tus pacientes.</p>
            </div>
            <span class="badge"><span class="material-symbols-outlined">workspace_premium</span>Especialista activo</span>
        </section>

        <div class="profile-layout">
            <aside class="profile-aside">
                <div class="profile-avatar">
                    <img id="avatarPreview" src="{{ $user->avatar ? asset('storage/'.$user->avatar) : asset('img/doctor1.jpg') }}" alt="Avatar">
                    <div class="profile-avatar__overlay" id="changePhoto">Cambiar foto</div>
                </div>
                <div>
                    <h2>{{ $user->name }}</h2>
                    <span>{{ $user->email }}</span>
                </div>
                @php
                    $especialidades = ($user->especialidades ?? collect())->pluck('nombre')->all();
                @endphp
                @if(count($especialidades))
                    <div class="chips">
                        @foreach($especialidades as $esp)
                            <span class="chip">{{ $esp }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="summary">
                    @if($user->telefono)
                        <div class="summary-item"><span class="material-symbols-outlined">call</span>{{ $user->telefono }}</div>
                    @endif
                    @if($user->direccion)
                        <div class="summary-item"><span class="material-symbols-outlined">location_on</span>{{ $user->direccion }}</div>
                    @endif
                    @if(!is_null($user->precio_consulta))
                        <div class="summary-item"><span class="material-symbols-outlined">payments</span>Precio: ${{ number_format($user->precio_consulta,2) }} USD</div>
                    @endif
                </div>
            </aside>

            <section class="profile-card">
                <div class="profile-card__header">
                    <h3>Datos personales y profesionales</h3>
                    <span>Todos los campos pueden editarse en cualquier momento.</span>
                </div>

                @if(session('success'))
                    <div class="alerts"><div class="alert success">{{ session('success') }}</div></div>
                @endif
                @if ($errors->any())
                    <div class="alerts">
                        <div class="alert danger">
                            <ul style="margin: 0 0 0 18px; padding: 0;">
                                @foreach ($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('doctor.perfil.update') }}" enctype="multipart/form-data">
                    @csrf
                    <input id="avatarInput" class="hidden" type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp">

                    <div class="profile-card__body">
                        <section class="section">
                            <h4 class="section-title"><span class="material-symbols-outlined">badge</span>Identidad</h4>
                            <div class="profile-grid">
                                <div class="field-box">
                                    <label>Nombre</label>
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                                    @error('name')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field-box">
                                    <label>Correo</label>
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                                    @error('email')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field-box">
                                    <label>Teléfono</label>
                                    <input type="tel" name="telefono" value="{{ old('telefono', $user->telefono) }}"
                                           inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10"
                                           placeholder="0998740927" title="Debe contener exactamente 10 dígitos">
                                    <div class="help">Formato: 10 dígitos.</div>
                                    @error('telefono')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field-box">
                                    <label>Número de Cédula</label>
                                    <input type="text" name="dni" value="{{ old('dni', $user->dni) }}"
                                           inputmode="numeric" pattern="\d{10}" minlength="10" maxlength="10"
                                           placeholder="1723456789" title="Debe contener exactamente 10 dígitos">
                                    <div class="help">Exactamente 10 dígitos.</div>
                                    @error('dni')<div class="error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </section>

                        <section class="section">
                            <h4 class="section-title"><span class="material-symbols-outlined">home_pin</span>Información adicional</h4>
                            <div class="profile-grid">
                                <div class="field-box">
                                    <label>Dirección</label>
                                    <input type="text" name="direccion" value="{{ old('direccion', $user->direccion) }}">
                                    @error('direccion')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field-box">
                                    <label>Fecha de nacimiento</label>
                                    <input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento', optional($user->fecha_nacimiento)->toDateString()) }}">
                                    @error('fecha_nacimiento')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field-box">
                                    <label>Sexo</label>
                                    <select name="sexo">
                                        <option value="">Seleccionar</option>
                                        <option value="Masculino" {{ old('sexo', $user->sexo)=='Masculino'?'selected':'' }}>Masculino</option>
                                        <option value="Femenino"  {{ old('sexo', $user->sexo)=='Femenino'?'selected':'' }}>Femenino</option>
                                        <option value="Otro"      {{ old('sexo', $user->sexo)=='Otro'?'selected':'' }}>Otro</option>
                                    </select>
                                    @error('sexo')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                {{-- NUEVO: Precio de consulta (USD) --}}
                                <div class="field-box">
                                    <label>Precio de consulta (USD)</label>
                                    <input type="number" name="precio_consulta" step="0.01" min="0"
                                           value="{{ old('precio_consulta', $user->precio_consulta) }}"
                                           placeholder="Ej: 25.00">
                                    <div class="help">Moneda fija: USD.</div>
                                    @error('precio_consulta')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <input type="hidden" name="moneda" value="USD">
                            </div>
                        </section>
                    </div>

                    <div class="profile-actions">
                        <a class="btn btn-secondary" href="{{ route('doctor.dashboard') }}">Regresar</a>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const changePhoto = document.getElementById('changePhoto');
            const input = document.getElementById('avatarInput');
            const preview = document.getElementById('avatarPreview');
            if (changePhoto && input && preview) {
                changePhoto.addEventListener('click', () => input.click());
                input.addEventListener('change', (e) => {
                    const file = e.target.files?.[0];
                    if (!file) return;
                    preview.src = URL.createObjectURL(file);
                });
            }
        });
    </script>
@endpush

{{-- resources/views/admin/users/form.blade.php --}}
@php($u = $user ?? new \App\Models\User)
@csrf
<div class="grid">
  <div>
    <label>Nombre</label>
    <input class="input" name="name" value="{{ old('name', $u->name) }}" required>
    @error('name')<small style="color:#b91c1c">{{ $message }}</small>@enderror
  </div>
  <div>
    <label>Email</label>
    <input class="input" name="email" type="email" value="{{ old('email', $u->email) }}" required>
    @error('email')<small style="color:#b91c1c">{{ $message }}</small>@enderror
  </div>

  @if(!$user)
  <div>
    <label>Contraseña</label>
    <input class="input" name="password" type="password" required>
    @error('password')<small style="color:#b91c1c">{{ $message }}</small>@enderror
  </div>
  <div>
    <label>Confirmación</label>
    <input class="input" name="password_confirmation" type="password" required>
  </div>
  @endif

  <div>
    <label>Teléfono</label>
    <input class="input" name="telefono" value="{{ old('telefono', $u->telefono) }}">
  </div>
  <div>
    <label>Cédula</label>
    <input class="input" name="dni" value="{{ old('dni', $u->dni) }}">
  </div>
  <div class="full">
    <label>Dirección</label>
    <input class="input" name="direccion" value="{{ old('direccion', $u->direccion) }}">
  </div>
  <div>
    <label>Fecha de nacimiento</label>
    <input class="input" name="fecha_nacimiento" type="date"
           value="{{ old('fecha_nacimiento', $u?->fecha_nacimiento?->format('Y-m-d')) }}">
  </div>
  <div>
    <label>Sexo</label>
    <select class="input" name="sexo">
      @foreach(['Masculino','Femenino','Otro'] as $sx)
        <option value="{{ $sx }}" @selected(old('sexo', $u->sexo)===$sx)>{{ $sx }}</option>
      @endforeach
    </select>
  </div>

  <div>
    <label>Rol</label>
    <select class="input" name="role_id" id="role_id" required onchange="toggleDoctorFields()" {{ request('role') ? 'disabled' : '' }}>
      @foreach($roles as $r)
        <option value="{{ $r->id }}"
          @selected(old('role_id', optional($u->roles->first())->id ?? optional($roles->firstWhere('name',request('role')))->id)==$r->id)>
          {{ ucfirst($r->name) }}
        </option>
      @endforeach
    </select>
    @if(request('role'))
      <input type="hidden" name="role_id" value="{{ optional($roles->firstWhere('name',request('role')))->id }}">
    @endif
  </div>

  <div id="doctor-only-esp" style="display:none">
    <label>Especialidad (solo Doctor)</label>
    <select class="input" name="especialidad_id">
      <option value="">—</option>
      @foreach(($especialidades ?? []) as $e)
        <option value="{{ $e->id }}" @selected(old('especialidad_id', optional($u->especialidades->first())->id ?? '')==$e->id)>{{ $e->nombre }}</option>
      @endforeach
    </select>
  </div>

  <div id="doctor-only-precio" style="display:none">
    <label>Precio consulta (USD)</label>
    <input class="input" name="precio_consulta" type="number" step="0.01" min="0" value="{{ old('precio_consulta', $u->precio_consulta) }}">
  </div>
</div>

@push('scripts')
<script>
function roleText(){
  const sel=document.getElementById('role_id');
  if(!sel) return '';
  return sel.options[sel.selectedIndex]?.text?.toLowerCase() || '';
}
function toggleDoctorFields(){
  const preset='{{ strtolower((string)request("role")) }}';
  const isDoctor = preset==='doctor' || roleText().includes('doctor');
  document.getElementById('doctor-only-esp').style.display = isDoctor ? '' : 'none';
  document.getElementById('doctor-only-precio').style.display = isDoctor ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleDoctorFields);
</script>
@endpush

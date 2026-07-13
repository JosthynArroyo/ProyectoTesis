@php
    $isActive = (bool) $dep->activo;
@endphp

<div class="card p-6 flex flex-col justify-between space-y-4 relative {{ $isActive ? '' : 'opacity-70' }}">
    <div>
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $dep->esMenor() ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600' }}">
                    @if($dep->esMenor())
                        <i class="ri-user-smile-line text-2xl"></i>
                    @else
                        <i class="ri-user-star-line text-2xl"></i>
                    @endif
                </div>
                <div class="min-w-0">
                    <h4 class="truncate font-semibold text-gray-900 leading-tight">{{ $dep->nombre }}</h4>
                    <span class="mt-1 inline-flex badge neutral text-xs">{{ ucfirst($dep->parentesco) }}</span>
                </div>
            </div>
            <span class="badge {{ $isActive ? 'success' : 'warning' }}">
                {{ $isActive ? 'Activo' : 'Inactivo' }}
            </span>
        </div>

        <div class="mt-4 space-y-2 text-sm text-gray-600">
            <div class="flex items-center gap-2">
                <i class="ri-id-card-line text-gray-400"></i>
                <span>Cédula: <strong>{{ $dep->dni }}</strong></span>
            </div>
            <div class="flex items-center gap-2">
                <i class="ri-calendar-event-line text-gray-400"></i>
                <span>Edad: <strong>{{ $dep->etiquetaEdad() }}</strong> ({{ $dep->fecha_nacimiento->format('d/m/Y') }})</span>
            </div>
            @if($dep->telefono_emergencia)
                <div class="flex items-center gap-2">
                    <i class="ri-phone-line text-gray-400"></i>
                    <span>Contacto: {{ $dep->telefono_emergencia }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-gray-100">
        <a href="{{ route('paciente.dependientes.edit', $dep->id) }}" class="btn btn-outline btn-sm flex-1 text-center justify-center">
            <i class="ri-edit-line"></i> Editar
        </a>

        @if($isActive)
            <form action="{{ route('paciente.dependientes.deactivate', $dep->id) }}" method="POST" class="flex-1">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-sm w-full justify-center btn-outline text-rose-600 hover:bg-rose-50">
                    <i class="ri-eye-off-line"></i> Desactivar
                </button>
            </form>
        @else
            <form action="{{ route('paciente.dependientes.activate', $dep->id) }}" method="POST" class="flex-1">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-sm w-full justify-center btn-primary">
                    <i class="ri-eye-line"></i> Activar
                </button>
            </form>
        @endif
    </div>

    <form
        action="{{ route('paciente.dependientes.destroy', $dep->id) }}"
        method="POST"
        onsubmit="return confirm('Eliminar este dependiente es una accion definitiva y distinta de desactivar. Solo continuara si no tiene historial medico asociado. ¿Deseas continuar?');"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-sm w-full justify-center btn-danger">
            <i class="ri-delete-bin-line"></i> Eliminar
        </button>
    </form>
</div>

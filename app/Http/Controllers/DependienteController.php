<?php

namespace App\Http\Controllers;

use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Services\ProfileAvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DependienteController extends Controller
{
    public function index()
    {
        $dependientes = $this->ownedDependientesQuery()->orderBy('nombre')->get();
        $limiteAlcanzado = $dependientes->count() >= Dependiente::MAX_POR_USUARIO;

        return view('paciente.dependientes.index', compact('dependientes', 'limiteAlcanzado'));
    }

    public function create()
    {
        $dependientes = $this->ownedDependientesQuery()->get();
        if ($dependientes->count() >= Dependiente::MAX_POR_USUARIO) {
            return redirect()->route('paciente.dependientes.index')
                ->with('error', 'Has alcanzado el límite máximo de dependientes (10).');
        }

        $dependiente = new Dependiente();

        return view('paciente.dependientes.form', compact('dependiente'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($this->ownedDependientesQuery()->count() >= Dependiente::MAX_POR_USUARIO) {
            return redirect()->route('paciente.dependientes.index')
                ->with('error', 'Has alcanzado el límite máximo de dependientes registrados.');
        }

        $rules = \App\Support\ValidationRules::dependiente(false, null, $user->id);
        $request->validate($rules, [
            'nombre.required' => 'El nombre es obligatorio.',
            'dni.required' => 'El número de cédula es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'parentesco.required' => 'El parentesco es obligatorio.',
            'parentesco.in' => 'El parentesco seleccionado no es válido.',
            'avatar.image' => 'El archivo debe ser una imagen válida.',
            'avatar.mimes' => 'Solo se aceptan imágenes JPG, PNG y WebP.',
            'avatar.max' => 'La imagen no debe superar los 5 MB.',
        ]);

        try {
            DB::beginTransaction();

            $data = $request->except(['avatar', '_token']);
            $dependiente = new Dependiente($data);
            $dependiente->user_id = $user->id;
            $dependiente->activo = true;
            $dependiente->save();

            ClinicalRecord::create([
                'dependiente_id' => $dependiente->id,
                'patient_id' => null,
                'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
            ]);

            if ($request->hasFile('avatar')) {
                app(ProfileAvatarService::class)->replaceForDependent($dependiente, $request->file('avatar'));
            }

            DB::commit();

            return redirect()->route('paciente.dependientes.index')
                ->with('success', 'Dependiente registrado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'Ocurrió un error al registrar al dependiente: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $dependiente = $this->ownedDependientesQuery()->whereKey($id)->firstOrFail();

        return view('paciente.dependientes.form', compact('dependiente'));
    }

    public function update(Request $request, $id)
    {
        $dependiente = $this->ownedDependientesQuery()->whereKey($id)->firstOrFail();

        $rules = \App\Support\ValidationRules::dependiente(true, $dependiente->id, Auth::id());
        $request->validate($rules, [
            'nombre.required' => 'El nombre es obligatorio.',
            'dni.required' => 'El número de cédula es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'parentesco.required' => 'El parentesco es obligatorio.',
            'parentesco.in' => 'El parentesco seleccionado no es válido.',
            'avatar.image' => 'El archivo debe ser una imagen válida.',
            'avatar.mimes' => 'Solo se aceptan imágenes JPG, PNG y WebP.',
            'avatar.max' => 'La imagen no debe superar los 5 MB.',
        ]);

        $data = $request->except(['avatar', '_token', '_method']);
        $dependiente->fill($data);
        $dependiente->save();

        if ($request->hasFile('avatar')) {
            app(ProfileAvatarService::class)->replaceForDependent($dependiente, $request->file('avatar'));
        }

        return redirect()->route('paciente.dependientes.index')
            ->with('success', 'Datos del dependiente actualizados.');
    }

    public function activate($id)
    {
        $dependiente = $this->ownedDependientesQuery()->whereKey($id)->firstOrFail();
        $dependiente->forceFill(['activo' => true])->save();

        return redirect()->route('paciente.dependientes.index')->with('success', 'Dependiente activado.');
    }

    public function deactivate($id)
    {
        $dependiente = $this->ownedDependientesQuery()->whereKey($id)->firstOrFail();
        $dependiente->forceFill(['activo' => false])->save();

        return redirect()->route('paciente.dependientes.index')->with('success', 'Dependiente desactivado.');
    }

    public function destroy($id)
    {
        $dependiente = $this->ownedDependientesQuery()->with([
            'citas:id,dependiente_id',
            'clinicalRecord',
        ])->findOrFail($id);

        $blockers = $this->dependienteDeleteBlockers($dependiente);

        if ($blockers !== []) {
            if ($dependiente->activo) {
                $dependiente->forceFill(['activo' => false])->save();
            }

            return redirect()->route('paciente.dependientes.index')
                ->with('error', 'No se puede eliminar este dependiente porque tiene historial medico asociado: '.implode(', ', $blockers).'. Se mantendra desactivado.');
        }

        // 1. Guardar en memoria la clave cruda del avatar antes del borrado
        $previousAvatar = (string) $dependiente->getRawOriginal('avatar');

        // 2. Eliminar dependiente en la BD primero
        DB::transaction(function () use ($dependiente): void {
            $dependiente->delete();
        });

        // 3. Confirmada la eliminación de la BD, proceder con la limpieza R2 privada (capturando errores de R2)
        if ($previousAvatar !== '') {
            try {
                app(ProfileAvatarService::class)->deleteDependentR2Avatar($previousAvatar);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to clean up R2 avatar variants for deleted dependent', [
                    'dependiente_id' => $id,
                    'avatar_key' => $previousAvatar,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('paciente.dependientes.index')
            ->with('success', 'Dependiente eliminado definitivamente.');
    }

    private function ownedDependientesQuery()
    {
        return Dependiente::query()->where('user_id', Auth::id());
    }

    private function dependienteDeleteBlockers(Dependiente $dependiente): array
    {
        $blockers = [];

        $hasAppointments = $dependiente->citas->isNotEmpty();
        if ($hasAppointments) {
            $blockers[] = 'citas medicas registradas';
        }

        if ($dependiente->clinicalRecord) {
            $record = $dependiente->clinicalRecord;
            $hasClinicalEntries = $record->problems()->exists()
                || $record->medications()->exists()
                || $record->allergies()->exists()
                || $record->histories()->exists()
                || $record->alerts()->exists();

            if ($hasClinicalEntries) {
                $blockers[] = 'registros en expediente clinico';
            }
        }

        return $blockers;
    }
}

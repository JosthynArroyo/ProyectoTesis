<?php

namespace App\Http\Controllers;

use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DependienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

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
                ->with('error', 'Has alcanzado el lÃ­mite mÃ¡ximo de dependientes registrados.');
        }

        $dependiente = new Dependiente();

        return view('paciente.dependientes.form', compact('dependiente'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($this->ownedDependientesQuery()->count() >= Dependiente::MAX_POR_USUARIO) {
            return redirect()->route('paciente.dependientes.index')
                ->with('error', 'Has alcanzado el lÃ­mite mÃ¡ximo de dependientes registrados.');
        }

        $rules = \App\Support\ValidationRules::dependiente(false, null, $user->id);
        $request->validate($rules, [
            'nombre.required' => 'El nombre es obligatorio.',
            'dni.required' => 'El número de cédula es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'parentesco.required' => 'El parentesco es obligatorio.',
            'parentesco.in' => 'El parentesco seleccionado no es válido.',
        ]);

        try {
            DB::beginTransaction();

            $dependiente = new Dependiente($request->all());
            $dependiente->user_id = $user->id;
            $dependiente->activo = true;
            $dependiente->save();

            ClinicalRecord::create([
                'dependiente_id' => $dependiente->id,
                'patient_id' => null,
                'allergies_status' => ClinicalRecord::ALLERGIES_UNKNOWN,
            ]);

            DB::commit();

            return redirect()->route('paciente.dependientes.index')
                ->with('success', 'Dependiente registrado correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->withErrors(['error' => 'OcurriÃ³ un error al registrar al dependiente: ' . $e->getMessage()]);
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
        ]);

        $dependiente->fill($request->all());
        $dependiente->save();

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

        DB::transaction(function () use ($dependiente): void {
            $dependiente->delete();
        });

        return redirect()->route('paciente.dependientes.index')
            ->with('success', 'Dependiente eliminado definitivamente.');
    }

    private function ownedDependientesQuery()
    {
        return Dependiente::query()->where('user_id', Auth::id());
    }

    /**
     * @return array<int, string>
     */
    private function dependienteDeleteBlockers(Dependiente $dependiente): array
    {
        $blockers = [];

        if ($dependiente->citas()->exists()) {
            $blockers[] = 'citas';
        }

        $record = $dependiente->clinicalRecord;
        if ($record) {
            if ($record->allergies()->exists()) {
                $blockers[] = 'alergias';
            }
            if ($record->histories()->exists()) {
                $blockers[] = 'antecedentes';
            }
            if ($record->problems()->exists()) {
                $blockers[] = 'problemas clinicos';
            }
            if ($record->medications()->exists()) {
                $blockers[] = 'medicacion';
            }
            if ($record->alerts()->exists()) {
                $blockers[] = 'alertas';
            }
            if ($record->soapNotes()->exists()) {
                $blockers[] = 'notas clinicas';
            }
            if ($record->recipes()->exists()) {
                $blockers[] = 'recetas';
            }
            if ($record->medicalCertificates()->exists()) {
                $blockers[] = 'certificados medicos';
            }
            if ($record->legacyLabOrders()->exists()) {
                $blockers[] = 'ordenes de laboratorio';
            }
            if ($record->labOrders()->exists()) {
                $blockers[] = 'pedidos de laboratorio';
            }
            if ($record->medicalOrders()->exists()) {
                $blockers[] = 'solicitudes medicas';
            }
        }

        return array_values(array_unique($blockers));
    }
}

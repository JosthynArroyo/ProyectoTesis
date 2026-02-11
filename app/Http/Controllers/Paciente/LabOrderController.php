<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\MedicalOrder;
use Database\Seeders\LabTestsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LabOrderController extends Controller
{
    public function create()
    {
        $patient = Auth::user();
        $labTests = LabTest::where('activo', true)->orderBy('nombre')->get();
        if ($labTests->isEmpty()) {
            (new LabTestsSeeder())->run();
            $labTests = LabTest::where('activo', true)->orderBy('nombre')->get();
        }

        $missingPrep = $labTests->filter(function ($test) {
            return trim((string) $test->preparacion_default) === '';
        });
        if ($missingPrep->isNotEmpty()) {
            logger()->warning('Catalogo de examenes sin preparacion registrada.', [
                'lab_test_ids' => $missingPrep->pluck('id')->all(),
            ]);
        }
        $medicalOrders = MedicalOrder::with(['labTest', 'doctor'])
            ->where('patient_id', $patient->id)
            ->where('status', MedicalOrder::STATUS_PENDIENTE)
            ->whereHas('labTest', function ($query) {
                $query->where('activo', true);
            })
            ->orderByDesc('created_at')
            ->get();

        $defaultSource = old('source');
        if (!$defaultSource) {
            $defaultSource = $medicalOrders->isNotEmpty()
                ? LabOrder::SOURCE_MEDICAL_ORDER
                : LabOrder::SOURCE_ROUTINE;
        }

        return view('paciente.laboratorio.solicitar', compact('labTests', 'medicalOrders', 'defaultSource'));
    }

    public function store(Request $request)
    {
        $patientId = Auth::id();
        $source = $request->input('source');

        $rules = [
            'source' => ['required', Rule::in([LabOrder::SOURCE_MEDICAL_ORDER, LabOrder::SOURCE_ROUTINE])],
            'priority' => ['required', Rule::in(['normal', 'urgente'])],
            'lab_test_id' => [
                'required',
                'integer',
                Rule::exists('lab_tests', 'id')->where('activo', true),
            ],
            'medical_order_id' => ['nullable'],
            'doctor_notes' => ['prohibited'],
            'preparacion' => ['prohibited'],
            'indicaciones' => ['prohibited'],
            'doctor_id' => ['prohibited'],
        ];

        if ($source === LabOrder::SOURCE_MEDICAL_ORDER) {
            $rules['medical_order_id'] = [
                'required',
                Rule::exists('medical_orders', 'id')
                    ->where('patient_id', $patientId)
                    ->where('status', MedicalOrder::STATUS_PENDIENTE),
            ];
        } else {
            $rules['medical_order_id'] = ['prohibited'];
        }

        $validated = $request->validate(
            $rules,
            [
                'source.required' => 'Selecciona el origen de la solicitud.',
                'source.in' => 'Selecciona un origen valido para la solicitud.',
                'lab_test_id.required' => 'Selecciona el tipo de examen.',
                'lab_test_id.exists' => 'El examen seleccionado no es valido o no esta disponible.',
                'lab_test_id.integer' => 'Selecciona un examen valido.',
                'medical_order_id.required' => 'Selecciona una orden medica valida.',
                'medical_order_id.exists' => 'La orden medica seleccionada no es valida.',
            ],
            [
                'lab_test_id' => 'examen',
                'medical_order_id' => 'orden medica',
                'source' => 'origen',
                'priority' => 'prioridad',
            ]
        );

        $priority = $validated['priority'];

        $medicalOrder = null;
        $doctorId = null;
        $doctorNotes = null;
        $labTest = null;

        if ($source === LabOrder::SOURCE_MEDICAL_ORDER) {
            $medicalOrder = MedicalOrder::with('labTest')
                ->where('id', $validated['medical_order_id'])
                ->where('patient_id', $patientId)
                ->where('status', MedicalOrder::STATUS_PENDIENTE)
                ->firstOrFail();

            $labTest = $medicalOrder->labTest;
            if (!$labTest || !$labTest->activo) {
                return back()->withErrors(['medical_order_id' => 'La orden medica no tiene un examen valido.'])->withInput();
            }

            if ((int) $validated['lab_test_id'] !== (int) $labTest->id) {
                return back()->withErrors(['lab_test_id' => 'El examen debe coincidir con la orden medica.'])->withInput();
            }

            $doctorId = $medicalOrder->doctor_id;
            $doctorNotes = $medicalOrder->doctor_notes;
        } else {
            $labTest = LabTest::where('id', $validated['lab_test_id'])
                ->where('activo', true)
                ->firstOrFail();
            if ($labTest->requiere_orden || !$labTest->es_rutina || $labTest->tipo !== 'rutina') {
                return back()->withErrors([
                    'lab_test_id' => 'Este examen requiere orden medica. Cambia a "Con orden medica" o selecciona un examen de rutina.',
                ])->withInput();
            }
        }

        $prepSnapshot = trim((string) $labTest->preparacion_default);
        if ($prepSnapshot === '') {
            $prepSnapshot = 'Este examen no tiene preparacion registrada. Contacte a la clinica.';
            logger()->error('Lab test sin preparacion en catalogo.', [
                'lab_test_id' => $labTest->id,
                'patient_id' => $patientId,
            ]);
        }
        $indicacionesSnapshot = trim((string) $labTest->indicaciones_default);
        if ($indicacionesSnapshot === '') {
            $indicacionesSnapshot = 'Sin indicaciones adicionales para este examen.';
        }

        DB::transaction(function () use (
            $patientId,
            $source,
            $priority,
            $doctorId,
            $doctorNotes,
            $medicalOrder,
            $labTest,
            $prepSnapshot,
            $indicacionesSnapshot
        ) {
            $labOrder = LabOrder::create([
                'patient_id' => $patientId,
                'source' => $source,
                'doctor_id' => $doctorId,
                'medical_order_id' => $medicalOrder?->id,
                'priority' => $priority,
                'status' => LabOrder::STATUS_PENDIENTE_TOMA,
                'doctor_notes' => $doctorNotes,
            ]);

            LabOrderItem::create([
                'lab_order_id' => $labOrder->id,
                'lab_test_id' => $labTest->id,
                'preparacion_snapshot' => $prepSnapshot,
                'indicaciones_snapshot' => $indicacionesSnapshot,
            ]);

            if ($medicalOrder) {
                $medicalOrder->update(['status' => MedicalOrder::STATUS_USADA]);
            }
        });

        return redirect()->route('paciente.dashboard')
            ->with('success', 'Solicitud de examen registrada.');
    }
}

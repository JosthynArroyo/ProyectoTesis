<?php

namespace App\Services\Chatbot;

use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\User;
use App\Services\ProfessionalScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ChatbotCatalogService
{
    public function __construct(
        protected ProfessionalScheduleService $scheduleService
    ) {}

    public function especialidades(): Collection
    {
        return Especialidad::orderBy('nombre')->get(['id', 'nombre']);
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad): array
    {
        $rol = $especialidad->isLaboratorioClinico() ? 'laboratorio' : 'doctor';

        $doctores = User::query()
            ->role($rol)
            ->onlyActive()
            ->whereHas('especialidades', fn ($q) => $q->where('especialidades.id', $especialidad->id))
            ->orderBy('name')
            ->get(['id', 'name', 'precio_consulta', 'moneda']);

        if ($doctores->isEmpty()) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => 'No contamos con médicos activos para esta especialidad de momento.',
            ];
        }

        return [
            'ok' => true,
            'status' => 200,
            'especialidad' => $especialidad->nombre,
            'doctores' => $doctores->map(function ($d) {
                $precioDefinido = ! is_null($d->precio_consulta);
                $moneda = $d->moneda ?? 'USD';

                return [
                    'id' => $d->id,
                    'nombre' => $d->name,
                    'precio' => $precioDefinido ? (float) $d->precio_consulta : null,
                    'moneda' => $moneda,
                    'precio_format' => $precioDefinido
                        ? sprintf('$%s %s', number_format((float) $d->precio_consulta, 2), $moneda)
                        : 'Tarifa no disponible',
                ];
            })->values(),
        ];
    }

    public function fechasDisponibles(User $doctor): array
    {
        try {
            abort_unless($doctor->isActive() && ($doctor->hasRole('doctor') || $doctor->hasRole('laboratorio')), 404);

            $tz = config('app.timezone', 'America/Guayaquil');
            $hoy = Carbon::today($tz);
            if ($doctor->hasRole('laboratorio')) {
                $fechas = [];

                for ($i = 0; $i < 30; $i++) {
                    $fecha = $hoy->copy()->addDays($i)->toDateString();
                    $slotsLibres = $this->calcularSlotsDisponibles($doctor->id, $fecha);
                    if (empty($slotsLibres)) {
                        continue;
                    }

                    $fechas[] = [
                        'value' => $fecha,
                        'label' => Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM'),
                    ];

                    if (count($fechas) >= 7) {
                        break;
                    }
                }

                if (empty($fechas)) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'message' => 'Este médico no tiene horarios libres próximamente.',
                    ];
                }

                return [
                    'ok' => true,
                    'status' => 200,
                    'doctor' => ['id' => $doctor->id, 'nombre' => $doctor->name],
                    'fechas' => $fechas,
                ];
            }

            $horarios = Horario::where('doctor_id', $doctor->id)
                ->whereDate('fecha', '>=', $hoy)
                ->orderBy('fecha')
                ->limit(30)
                ->get()
                ->groupBy(fn ($h) => Carbon::parse($h->fecha)->toDateString());

            $fechas = [];
            foreach ($horarios as $fecha => $bloques) {
                $slotsLibres = $this->calcularSlotsDisponibles($doctor->id, $fecha, $bloques);
                if (empty($slotsLibres)) {
                    continue;
                }

                $fechas[] = [
                    'value' => $fecha,
                    'label' => Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM'),
                ];

                if (count($fechas) >= 7) {
                    break;
                }
            }

            if (empty($fechas)) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'message' => 'Este médico no tiene horarios libres próximamente.',
                ];
            }

            return [
                'ok' => true,
                'status' => 200,
                'doctor' => ['id' => $doctor->id, 'nombre' => $doctor->name],
                'fechas' => $fechas,
            ];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error en Chatbot al consultar fechasDisponibles: '.$e->getMessage());

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Ocurrió un error inesperado al cargar las fechas disponibles.',
            ];
        }
    }

    public function calcularSlotsDisponibles(int $doctorId, string $fecha, $bloques = null): array
    {
        return collect($this->scheduleService->buildSlotsForDate(
            $doctorId,
            $fecha,
            config('app.timezone', 'America/Guayaquil')
        ))
            ->filter(fn ($slot) => ($slot['estado'] ?? null) === 'libre')
            ->pluck('hora')
            ->values()
            ->all();
    }
}

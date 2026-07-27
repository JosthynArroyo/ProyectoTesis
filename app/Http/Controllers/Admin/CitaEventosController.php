<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CitaEvento;
use App\Models\User;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CitaEventosController extends Controller
{
    public function index(Request $request)
    {
        ['tipo' => $tipo, 'estado' => $estado, 'doctorId' => $doctorId, 'paciente' => $paciente, 'desde' => $desde, 'hasta' => $hasta, 'q' => $q] = $this->normalizeFilters($request);

        $ev = $this->query($request)
            ->paginate(25)
            ->withQueryString();

        $doctores = User::whereHas('roles', fn ($r) => $r->where('name', 'doctor'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.cambios-citas.index', compact('ev', 'tipo', 'estado', 'doctorId', 'paciente', 'desde', 'hasta', 'q', 'doctores'));
    }

    public function exportExcel(Request $request)
    {
        $rows = $this->query($request)->get();

        $data = [[
            'Fecha/Hora',
            'Evento',
            'Cita',
            'Paciente',
            'Doctor',
            'De estado',
            'A estado',
            'Valor anterior',
            'Valor nuevo',
            'Comentario',
            'De fecha-hora',
            'A fecha-hora',
        ]];

        foreach ($rows as $r) {
            // created_at seguro
            $createdStr = $r->created_at instanceof Carbon
                ? $r->created_at->format('Y-m-d H:i')
                : ($r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d H:i') : '');

            $deEstado = $r->de_estado ?? '';
            $aEstado = $r->a_estado ?? '';

            $deFecha = $r->de_fecha ?? null;
            $aFecha = $r->a_fecha ?? null;
            $deHora = $r->de_hora ?? '';
            $aHora = $r->a_hora ?? '';

            // Formateo defensivo de fecha
            $deFechaStr = $deFecha
                ? ($deFecha instanceof Carbon ? $deFecha->format('Y-m-d') : Carbon::parse($deFecha)->format('Y-m-d'))
                : '';
            $aFechaStr = $aFecha
                ? ($aFecha instanceof Carbon ? $aFecha->format('Y-m-d') : Carbon::parse($aFecha)->format('Y-m-d'))
                : '';

            $data[] = [
                $createdStr,
                $r->tipo,
                '#'.$r->cita_id,
                optional($r->cita?->paciente)->name,
                optional($r->cita?->doctor)->name,
                $deEstado,
                $aEstado,
                $r->valor_anterior,
                $r->valor_nuevo,
                $r->comentario,
                trim($deFechaStr.' '.$deHora),
                trim($aFechaStr.' '.$aHora),
            ];
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data, null, 'A1', true);
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->setTitle('Cambios de Citas');

        $writer = new Xlsx($spreadsheet);
        $filename = 'cambios_citas_'.now()->format('Ymd_His').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }

    public function exportPdf(Request $request)
    {
        $rows = $this->query($request)->get();
        $filters = $this->normalizeFilters($request);
        $eventLabels = [
            'agendada' => 'Agendada',
            'confirmada' => 'Confirmada',
            'cancelada' => 'Cancelada',
            'realizada' => 'Realizada',
            'no_se_presento' => 'No se presento',
            'reprogramada' => 'Reprogramada',
            'prioridad_manual' => 'Prioridad manual',
        ];

        $html = view('admin.cambios-citas.pdf', [
            'rows' => $rows,
            'tipo' => $filters['tipo'],
            'estado' => $filters['estado'],
            'doctorId' => $filters['doctorId'],
            'paciente' => $filters['paciente'],
            'desde' => $filters['desde'],
            'hasta' => $filters['hasta'],
            'q' => $filters['q'],
            'pdfCss' => $this->loadPdfCss('admin/cambios-citas-pdf.css'),
            'eventLabels' => $eventLabels,
        ])->render();

        $opt = new Options;
        $opt->set('isRemoteEnabled', true);
        $opt->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $pdf->stream('cambios_citas_'.now()->format('Ymd_His').'.pdf');
        exit;
    }

    /** Construccion comun de filtros para index/export */
    private function query(Request $request)
    {
        ['tipo' => $tipo, 'estado' => $estado, 'doctorId' => $doctorId, 'paciente' => $paciente, 'desde' => $desde, 'hasta' => $hasta, 'q' => $q] = $this->normalizeFilters($request);

        return CitaEvento::query()
            ->whereHas('cita')
            ->with([
                'cita' => fn ($cita) => $cita->with([
                    'paciente:id,name,email,dni',
                    'doctor:id,name',
                ]),
            ])
            ->when($tipo !== '', fn ($qq) => $qq->where('tipo', $tipo))
            ->when($estado !== '', function ($qq) use ($estado) {
                $qq->where(function ($w) use ($estado) {
                    $w->where('a_estado', $estado)
                        ->orWhere('de_estado', $estado);
                });
            })
            ->when($doctorId, fn ($qq) => $qq->whereHas('cita', fn ($w) => $w->where('doctor_id', $doctorId)))
            ->when($paciente !== '', fn ($qq) => $qq->whereHas('cita.paciente', fn ($w) => $w->where('name', 'like', '%'.$paciente.'%')))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(function ($w) use ($like) {
                    $w->where('valor_anterior', 'like', $like)
                        ->orWhere('valor_nuevo', 'like', $like)
                        ->orWhere('comentario', 'like', $like)
                        ->orWhereHas('cita', fn ($c) => $c->where('id', 'like', $like))
                        ->orWhereHas('cita.paciente', fn ($c) => $c->where('email', 'like', $like)->orWhere('dni', 'like', $like))
                        ->orWhereHas('cita.doctor', fn ($c) => $c->where('name', 'like', $like));
                });
            })
            ->when($desde, fn ($qq) => $qq->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn ($qq) => $qq->whereDate('created_at', '<=', $hasta))
            ->orderByDesc('created_at');
    }

    private function normalizeFilters(Request $request): array
    {
        $allowedTipos = ['agendada', 'confirmada', 'cancelada', 'realizada', 'no_se_presento', 'reprogramada', 'prioridad_manual'];
        $allowedEstados = ['pendiente', 'confirmada', 'cancelada', 'realizada', 'no_se_presento'];

        $tipo = trim($request->string('tipo')->toString());
        if ($tipo === 'all') {
            $tipo = '';
        }
        if (! in_array($tipo, array_merge([''], $allowedTipos), true)) {
            $tipo = '';
        }

        $estado = trim($request->string('estado')->toString());
        if ($estado === 'all') {
            $estado = '';
        }
        if (! in_array($estado, array_merge([''], $allowedEstados), true)) {
            $estado = '';
        }

        return [
            'tipo' => $tipo,
            'estado' => $estado,
            'doctorId' => $this->normalizePositiveInt($request->get('doctor_id')),
            'paciente' => $this->normalizeSearchTerm($request->get('paciente', '')),
            'desde' => $this->normalizeDateFilter($request->get('desde', '')),
            'hasta' => $this->normalizeDateFilter($request->get('hasta', '')),
            'q' => $this->normalizeSearchTerm($request->get('q', '')),
        ];
    }

    private function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $normalized === false ? null : (int) $normalized;
    }

    private function normalizeDateFilter(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function loadPdfCss(string $relativePath): string
    {
        $path = resource_path('css/'.$relativePath);

        return is_file($path) ? (file_get_contents($path) ?: '') : '';
    }
}

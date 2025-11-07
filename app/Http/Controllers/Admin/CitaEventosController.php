<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CitaEvento;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class CitaEventosController extends Controller
{
    public function index(Request $request)
    {
        $tipo     = $request->string('tipo')->toString();          // agendada|confirmada|cancelada|realizada|reprogramada
        $estado   = $request->string('estado')->toString();        // pendiente|confirmada|cancelada|realizada
        $doctorId = $request->integer('doctor_id') ?: null;
        $paciente = trim((string)$request->get('paciente', ''));   // nombre paciente
        $desde    = $request->get('desde');                        // YYYY-MM-DD
        $hasta    = $request->get('hasta');                        // YYYY-MM-DD
        $q        = trim((string)$request->get('q', ''));          // búsqueda libre (#cita, correo, dni)

        $ev = CitaEvento::with(['cita.paciente', 'cita.doctor'])
            ->when($tipo !== '', fn($qq) => $qq->where('tipo', $tipo))
            ->when($estado !== '', function ($qq) use ($estado) {
                $qq->where(function ($w) use ($estado) {
                    $w->where('a_estado', $estado)
                      ->orWhere('de_estado', $estado)
                      ->orWhere('estado_nuevo', $estado)
                      ->orWhere('estado_anterior', $estado);
                });
            })
            ->when($doctorId, fn($qq) => $qq->whereHas('cita', fn($w) => $w->where('doctor_id', $doctorId)))
            ->when($paciente !== '', fn($qq) => $qq->whereHas('cita.paciente', fn($w) => $w->where('name', 'like', '%'.$paciente.'%')))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(function ($w) use ($like) {
                    $w->whereHas('cita', fn($c) => $c->where('id', 'like', $like))
                      ->orWhereHas('cita.paciente', fn($c) => $c->where('email', 'like', $like)->orWhere('dni', 'like', $like))
                      ->orWhereHas('cita.doctor', fn($c) => $c->where('name', 'like', $like));
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('created_at', '<=', $hasta))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $doctores = User::whereHas('roles', fn($r) => $r->where('name', 'doctor'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.cambios-citas.index', compact('ev', 'tipo', 'estado', 'doctorId', 'paciente', 'desde', 'hasta', 'q', 'doctores'));
    }

    public function exportExcel(Request $request)
    {
        $rows = $this->query($request)->get();

        $data = [['Fecha/Hora', 'Evento', 'Cita', 'Paciente', 'Doctor', 'De estado', 'A estado', 'De fecha-hora', 'A fecha-hora']];

        foreach ($rows as $r) {
            // created_at seguro
            $createdStr = $r->created_at instanceof Carbon
                ? $r->created_at->format('Y-m-d H:i')
                : ($r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d H:i') : '');

            // Fallbacks para nombres alternos de columnas
            $deEstado = $r->de_estado ?? $r->estado_anterior ?? '';
            $aEstado  = $r->a_estado  ?? $r->estado_nuevo    ?? '';

            $deFecha  = $r->de_fecha  ?? $r->fecha_anterior ?? null;
            $aFecha   = $r->a_fecha   ?? $r->fecha_nueva    ?? null;
            $deHora   = $r->de_hora   ?? $r->hora_anterior  ?? '';
            $aHora    = $r->a_hora    ?? $r->hora_nueva     ?? '';

            // Formateo defensivo de fecha
            $deFechaStr = $deFecha ? ($deFecha instanceof Carbon ? $deFecha->format('Y-m-d') : Carbon::parse($deFecha)->format('Y-m-d')) : '';
            $aFechaStr  = $aFecha  ? ($aFecha  instanceof Carbon ? $aFecha->format('Y-m-d')  : Carbon::parse($aFecha)->format('Y-m-d'))  : '';

            $data[] = [
                $createdStr,
                $r->tipo,
                '#'.$r->cita_id,
                optional($r->cita?->paciente)->name,
                optional($r->cita?->doctor)->name,
                $deEstado,
                $aEstado,
                trim($deFechaStr.' '.$deHora),
                trim($aFechaStr.' '.$aHora),
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data, null, 'A1', true);
        foreach (range('A', 'I') as $col) {
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

        $html = view('admin.cambios-citas.pdf', [
            'rows'     => $rows,
            'tipo'     => $request->get('tipo'),
            'estado'   => $request->get('estado'),
            'doctorId' => $request->get('doctor_id'),
            'paciente' => $request->get('paciente'),
            'desde'    => $request->get('desde'),
            'hasta'    => $request->get('hasta'),
            'q'        => $request->get('q'),
        ])->render();

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);
        $opt->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $pdf->stream('cambios_citas_'.now()->format('Ymd_His').'.pdf');
        exit;
    }

    /** Construcción común de filtros para index/export */
    private function query(Request $request)
    {
        $tipo     = $request->string('tipo')->toString();
        $estado   = $request->string('estado')->toString();
        $doctorId = $request->integer('doctor_id') ?: null;
        $paciente = trim((string)$request->get('paciente', ''));
        $desde    = $request->get('desde');
        $hasta    = $request->get('hasta');
        $q        = trim((string)$request->get('q', ''));

        return CitaEvento::with(['cita.paciente', 'cita.doctor'])
            ->when($tipo !== '', fn($qq) => $qq->where('tipo', $tipo))
            ->when($estado !== '', function ($qq) use ($estado) {
                $qq->where(function ($w) use ($estado) {
                    $w->where('a_estado', $estado)
                      ->orWhere('de_estado', $estado)
                      ->orWhere('estado_nuevo', $estado)
                      ->orWhere('estado_anterior', $estado);
                });
            })
            ->when($doctorId, fn($qq) => $qq->whereHas('cita', fn($w) => $w->where('doctor_id', $doctorId)))
            ->when($paciente !== '', fn($qq) => $qq->whereHas('cita.paciente', fn($w) => $w->where('name', 'like', '%'.$paciente.'%')))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(function ($w) use ($like) {
                    $w->whereHas('cita', fn($c) => $c->where('id', 'like', $like))
                      ->orWhereHas('cita.paciente', fn($c) => $c->where('email', 'like', $like)->orWhere('dni', 'like', $like))
                      ->orWhereHas('cita.doctor', fn($c) => $c->where('name', 'like', $like));
                });
            })
            ->when($desde, fn($qq) => $qq->whereDate('created_at', '>=', $desde))
            ->when($hasta, fn($qq) => $qq->whereDate('created_at', '<=', $hasta))
            ->orderByDesc('created_at');
    }
}

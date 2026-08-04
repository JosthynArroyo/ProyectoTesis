<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Services\DatabaseBackup\BackupCoordinatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function index(Request $request)
    {
        $query = DatabaseBackup::query()->with('user')->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $backups = $query->paginate(15)->withQueryString();

        $lastSuccessful = DatabaseBackup::query()
            ->whereIn('status', [DatabaseBackup::STATUS_COMPLETED, DatabaseBackup::STATUS_VERIFIED])
            ->latest('completed_at')
            ->first();

        $lastSuccessfulDate = $lastSuccessful ? $lastSuccessful->completed_at?->timezone('America/Guayaquil') : null;
        $isOverdue = ! $lastSuccessful || ($lastSuccessful->completed_at && $lastSuccessful->completed_at->lt(now()->subHours(26)));

        $hasPendingOrProcessing = DatabaseBackup::query()
            ->whereIn('status', [DatabaseBackup::STATUS_PENDING, DatabaseBackup::STATUS_PROCESSING])
            ->exists();

        return view('superadmin.backups.index', [
            'backups' => $backups,
            'lastSuccessful' => $lastSuccessful,
            'lastSuccessfulDate' => $lastSuccessfulDate,
            'isOverdue' => $isOverdue,
            'hasPendingOrProcessing' => $hasPendingOrProcessing,
            'filters' => $request->only(['type', 'status', 'date']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pendingOrProcessing = DatabaseBackup::query()
            ->whereIn('status', [DatabaseBackup::STATUS_PENDING, DatabaseBackup::STATUS_PROCESSING])
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($pendingOrProcessing) {
            return redirect()
                ->route('superadmin.respaldos.index')
                ->with('error', 'Ya existe un respaldo en proceso. Por favor espere a que finalice.');
        }

        CreateDatabaseBackupJob::dispatch(DatabaseBackup::TYPE_MANUAL, (int) auth()->id());

        return redirect()
            ->route('superadmin.respaldos.index')
            ->with('success', 'El respaldo manual ha sido enviado a la cola de procesamiento en segundo plano.');
    }

    public function download(DatabaseBackup $backup): StreamedResponse|RedirectResponse
    {
        if (! $backup->file_path || ! Storage::disk('r2_backups')->exists($backup->file_path)) {
            return redirect()
                ->route('superadmin.respaldos.index')
                ->with('error', 'El archivo del respaldo no fue encontrado en el almacenamiento R2.');
        }

        $filename = basename($backup->file_path);

        return Storage::disk('r2_backups')->download($backup->file_path, $filename, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(DatabaseBackup $backup, BackupCoordinatorService $coordinator): RedirectResponse
    {
        try {
            $coordinator->verifyBackup($backup->uuid);

            return redirect()
                ->route('superadmin.respaldos.index')
                ->with('success', "Integridad verificada correctamente para el respaldo ID {$backup->uuid}.");
        } catch (\Throwable $e) {
            return redirect()
                ->route('superadmin.respaldos.index')
                ->with('error', "Fallo en la verificación de integridad: {$e->getMessage()}");
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentReceiptVerificationController extends Controller
{
    public function show(string $token): Response
    {
        $receipt = PaymentReceipt::query()
            ->with([
                'pago:id,estado,moneda,paciente_id',
                'pago.paciente:id,name',
            ])
            ->where('verification_token', $token)
            ->first();

        if (! $receipt) {
            abort(404);
        }

        $fullName = $receipt->pago?->paciente?->name;
        $protectedName = $this->formatProtectedName($fullName);

        $estadoActual = strtoupper((string) ($receipt->pago?->estado ?? 'PAGADO'));
        if ($estadoActual !== 'ANULADO') {
            $estadoActual = 'PAGADO';
        }

        $html = view('recibos.verificar', [
            'receipt' => $receipt,
            'protectedName' => $protectedName,
            'estadoActual' => $estadoActual,
        ])->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    private function formatProtectedName(?string $fullName): string
    {
        $trimmed = trim((string) $fullName);
        if ($trimmed === '') {
            return 'Paciente Registrado';
        }

        $parts = preg_split('/\s+/', $trimmed);
        if (! $parts || count($parts) === 0) {
            return 'Paciente Registrado';
        }

        if (count($parts) === 1) {
            return $parts[0];
        }

        $firstName = $parts[0];
        $lastNameInitial = mb_substr($parts[1], 0, 1, 'UTF-8');

        return $firstName . ' ' . mb_strtoupper($lastNameInitial, 'UTF-8') . '.';
    }
}

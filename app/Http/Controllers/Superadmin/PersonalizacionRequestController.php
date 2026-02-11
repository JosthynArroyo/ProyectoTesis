<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\FeatureAccessRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonalizacionRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->lower()->value();
        if ($status === 'all') {
            $status = '';
        }
        $query = FeatureAccessRequest::with(['user', 'reviewer'])
            ->forFeature('personalizacion')
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(15)->appends($request->query());

        return view('superadmin.solicitudes.personalizacion', compact('requests', 'status'));
    }

    public function approve(Request $request, FeatureAccessRequest $accessRequest)
    {
        if ($accessRequest->feature !== 'personalizacion') {
            return back()->withErrors(['Solicitud no valida.']);
        }

        $data = $request->validate([
            'req_id' => ['required', 'integer', Rule::in([$accessRequest->id])],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'no_expire' => ['required', 'boolean'],
        ]);

        $approvedUntil = null;
        if (! $request->boolean('no_expire')) {
            $hours = (int) ($data['duration_hours'] ?? 24);
            $approvedUntil = now()->addHours($hours);
        }

        $accessRequest->update([
            'status' => 'approved',
            'approved_until' => $approvedUntil,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'revoked_at' => null,
            'revoked_by' => null,
        ]);

        return back()->with('success', 'Solicitud aprobada.');
    }

    public function reject(Request $request, FeatureAccessRequest $accessRequest)
    {
        if ($accessRequest->feature !== 'personalizacion') {
            return back()->withErrors(['Solicitud no valida.']);
        }

        $data = $request->validate([
            'req_id' => ['required', 'integer', Rule::in([$accessRequest->id])],
            'notes' => ['required', 'string', 'max:255'],
        ]);

        $accessRequest->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
            'approved_until' => null,
        ]);

        return back()->with('success', 'Solicitud rechazada.');
    }

    public function revoke(Request $request, FeatureAccessRequest $accessRequest)
    {
        if ($accessRequest->feature !== 'personalizacion') {
            return back()->withErrors(['Solicitud no valida.']);
        }

        $accessRequest->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Acceso revocado.');
    }
}

<?php

namespace App\Services;

use App\Mail\CommercialInquiryReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CommercialContactService
{
    public const DEFAULT_RECIPIENT = 'alejandroucenriquez@gmail.com';

    public function __construct(
        private readonly ApplicationModeService $applicationMode
    ) {}

    /**
     * Determine if commercial contact sending is authorized for the request.
     * Requires active demo mode and a cryptographically valid HMAC signature for commercial context.
     */
    public function isAuthorizedCommercialRequest(Request $request): bool
    {
        if (! $this->applicationMode->isDemo()) {
            return false;
        }

        return $request->hasValidSignature() && $request->query('context') === 'commercial';
    }

    /**
     * Get the canonical recipient for commercial inquiries.
     */
    public function recipient(): string
    {
        return (string) (config('mail.commercial_to')
            ?: config('mail.contact_to')
            ?: self::DEFAULT_RECIPIENT);
    }

    /**
     * Send commercial inquiry email directly via the explicit SMTP mailer using the independent JA MedSys mailable.
     * Throws an exception if the SMTP transport fails, allowing the caller to handle delivery errors.
     */
    public function send(array $datos): void
    {
        $recipient = $this->recipient();
        $mailable = new CommercialInquiryReceived($datos);

        // Explicitly use the 'smtp' mailer so that DemoExternalEffectsGuard array mailer is safely bypassed
        // only for this authorized commercial request.
        Mail::mailer('smtp')->to($recipient)->send($mailable);
    }
}

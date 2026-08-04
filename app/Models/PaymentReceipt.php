<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentReceipt extends Model
{
    use HasFactory;

    public const ESTADO_EMITIDO = 'emitido';

    public const ESTADO_REEMITIDO = 'reemitido';

    protected $table = 'payment_receipts';

    protected $fillable = [
        'pago_id',
        'folio_recibo',
        'emitido_en',
        'emitido_por',
        'metodo_pago',
        'monto',
        'referencia_transaccion',
        'comprobante_path',
        'comprobante_disk',
        'pdf_path',
        'pdf_disk',
        'verification_token',
        'csv',
    ];

    protected $casts = [
        'emitido_en' => 'datetime',
        'monto' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PaymentReceiptLog::class, 'payment_receipt_id');
    }

    public function pdfEsValido(): bool
    {
        return Str::endsWith(Str::lower((string) $this->pdf_path), '.pdf');
    }
}

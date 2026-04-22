<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureAccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'feature',
        'status',
        'approved_until',
        'reviewed_at',
        'reviewed_by',
        'revoked_at',
        'revoked_by',
        'notes',
    ];

    protected $casts = [
        'approved_until' => 'datetime',
        'reviewed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeForFeature(Builder $query, string $feature): Builder
    {
        return $query->where('feature', $feature);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApprovedActive(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('approved_until')
                    ->orWhere('approved_until', '>', now());
            });
    }

    public function scopeApprovedExpired(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->whereNull('revoked_at')
            ->whereNotNull('approved_until')
            ->where('approved_until', '<=', now());
    }

    public function isActive(): bool
    {
        if ($this->status !== 'approved' || $this->revoked_at) {
            return false;
        }

        if (! $this->approved_until) {
            return true;
        }

        return $this->approved_until->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'approved'
            && ! $this->revoked_at
            && $this->approved_until
            && ! $this->approved_until->isFuture();
    }
}

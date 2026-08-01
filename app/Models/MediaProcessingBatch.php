<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaProcessingBatch extends Model
{
    use HasFactory;

    protected $table = 'media_processing_batches';

    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'status',
        'total_items',
        'processed_items',
        'payload',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'total_items' => 'integer',
        'processed_items' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPercentageAttribute(): int
    {
        if ($this->total_items <= 0) {
            return $this->status === 'completed' ? 100 : 0;
        }

        if ($this->status === 'completed') {
            return 100;
        }

        $percentage = (int) floor(($this->processed_items / $this->total_items) * 100);

        return min(99, max(0, $percentage));
    }

    public function getElapsedSecondsAttribute(): int
    {
        if ($this->finished_at) {
            return max(0, $this->created_at?->diffInSeconds($this->finished_at) ?? 0);
        }

        return max(0, $this->created_at?->diffInSeconds(now()) ?? 0);
    }

    public function getWaitingSecondsAttribute(): int
    {
        if (! $this->started_at) {
            return max(0, $this->created_at?->diffInSeconds(now()) ?? 0);
        }

        return max(0, $this->created_at?->diffInSeconds($this->started_at) ?? 0);
    }

    public function getProcessingSecondsAttribute(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $endAt = $this->finished_at ?: now();

        return max(0, $this->started_at->diffInSeconds($endAt));
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('superadmin');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    use HasFactory;

    protected $table = 'database_backups';

    public const TYPE_MANUAL = 'manual';
    public const TYPE_DAILY = 'daily';
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_VERIFIED = 'verified';

    protected $fillable = [
        'uuid',
        'type',
        'status',
        'disk',
        'file_path',
        'manifest_path',
        'file_size',
        'sha256',
        'started_at',
        'completed_at',
        'duration_seconds',
        'user_id',
        'error_message',
        'attempts',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'duration_seconds' => 'float',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_VERIFIED], true);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function formattedSize(): string
    {
        if (! $this->file_size) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}

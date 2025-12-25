<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ApplicationLog extends Model
{
    use HasFactory;

    /**
     * Disable updated_at timestamp.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'level',
        'channel',
        'message',
        'context',
        'exception_class',
        'exception_message',
        'stack_trace',
        'file',
        'line',
        'user_id',
        'ip_address',
        'user_agent',
        'url',
        'method',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Scope a query to only include logs of a specific level.
     */
    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include logs of a specific channel.
     */
    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope a query to only include error logs.
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->whereIn('level', ['emergency', 'alert', 'critical', 'error']);
    }

    /**
     * Scope a query to only include warning logs.
     */
    public function scopeWarnings(Builder $query): Builder
    {
        return $query->where('level', 'warning');
    }

    /**
     * Scope a query to only include info logs.
     */
    public function scopeInfo(Builder $query): Builder
    {
        return $query->where('level', 'info');
    }

    /**
     * Scope a query to filter by request ID.
     */
    public function scopeByRequestId(Builder $query, string $requestId): Builder
    {
        return $query->where('request_id', $requestId);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to get recent logs.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Check if log is an error.
     */
    public function isError(): bool
    {
        return in_array($this->level, ['emergency', 'alert', 'critical', 'error']);
    }

    /**
     * Check if log is a warning.
     */
    public function isWarning(): bool
    {
        return $this->level === 'warning';
    }

    /**
     * Get formatted log level with color.
     */
    public function getFormattedLevelAttribute(): string
    {
        $colors = [
            'emergency' => '🔴',
            'alert' => '🔴',
            'critical' => '🔴',
            'error' => '❌',
            'warning' => '⚠️',
            'notice' => '📢',
            'info' => 'ℹ️',
            'debug' => '🐛',
        ];

        return ($colors[$this->level] ?? '') . ' ' . strtoupper($this->level);
    }
}

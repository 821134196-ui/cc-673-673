<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sample extends Model
{
    use HasFactory;

    public const STATUS_NORMAL = 'normal';
    public const STATUS_QUARANTINED = 'quarantined';
    public const STATUS_FROZEN = 'frozen';
    public const STATUS_RELEASED = 'released';
    public const STATUS_DISCARDED = 'discarded';

    // 任何形式下不得领用的状态
    public const BLOCKED_STATUSES = [
        self::STATUS_QUARANTINED,
        self::STATUS_FROZEN,
        self::STATUS_DISCARDED,
    ];

    protected $fillable = [
        'code', 'name', 'batch_no', 'description', 'status',
        'recheck_deadline', 'quarantined_at', 'released_at', 'registered_by',
    ];

    protected $casts = [
        'recheck_deadline' => 'date',
        'quarantined_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        // 纯日期（如复检截止日）输出 Y-m-d；带时间的输出完整格式，便于前端展示
        return $date->format('H:i:s') === '00:00:00'
            ? $date->format('Y-m-d')
            : $date->format('Y-m-d H:i:s');
    }

    public function storageRecords(): HasMany
    {
        return $this->hasMany(StorageRecord::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(ObservationRecord::class);
    }

    public function quarantines(): HasMany
    {
        return $this->hasMany(Quarantine::class);
    }

    public function activeQuarantine(): ?Quarantine
    {
        return $this->quarantines()->whereNull('ended_at')->latest('started_at')->first();
    }

    public function usageRequests(): HasMany
    {
        return $this->hasMany(UsageRequest::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'photoable');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->whereIn('status', self::BLOCKED_STATUSES);
    }

    public function isUsageBlocked(): bool
    {
        return in_array($this->status, self::BLOCKED_STATUSES, true);
    }

    public function blockReason(): ?string
    {
        return match ($this->status) {
            self::STATUS_QUARANTINED => '样本处于隔离观察期，不得领用',
            self::STATUS_FROZEN => '样本复检未通过，处于继续冻结状态，不得领用',
            self::STATUS_DISCARDED => '样本已废弃，无法领用',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_NORMAL => '正常',
            self::STATUS_QUARANTINED => '隔离中',
            self::STATUS_FROZEN => '复检未通过·冻结',
            self::STATUS_RELEASED => '已解除隔离',
            self::STATUS_DISCARDED => '已废弃',
            default => $this->status,
        };
    }
}

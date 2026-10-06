<?php

namespace App\Models;

use App\Exceptions\SampleUsageBlockedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Sample extends Model
{
    use HasFactory;

    public const STATUS_IN_STOCK = 'in_stock';           // 正常在库
    public const STATUS_QUARANTINED = 'quarantined';     // 隔离中
    public const STATUS_RELEASED = 'released';           // 复检通过，放行
    public const STATUS_FROZEN = 'frozen';               // 复检未通过，继续冻结
    public const STATUS_DISCARDED = 'discarded';         // 已废弃

    public const STATUS_LABELS = [
        self::STATUS_IN_STOCK => '在库',
        self::STATUS_QUARANTINED => '隔离中',
        self::STATUS_RELEASED => '已放行',
        self::STATUS_FROZEN => '冻结（复检未通过）',
        self::STATUS_DISCARDED => '已废弃',
    ];

    protected $fillable = [
        'code', 'batch_no', 'name', 'description',
        'status', 'retest_due_at', 'quarantined_at',
    ];

    protected function casts(): array
    {
        return [
            'retest_due_at' => 'datetime',
            'quarantined_at' => 'datetime',
        ];
    }

    /** @return HasMany<StorageEvent> */
    public function storageEvents(): HasMany
    {
        return $this->hasMany(StorageEvent::class);
    }

    /** @return HasMany<Observation> */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /** @return HasMany<SampleUsage> */
    public function usages(): HasMany
    {
        return $this->hasMany(SampleUsage::class);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_IN_STOCK, self::STATUS_RELEASED], true);
    }

    public function blockReason(): ?string
    {
        return match ($this->status) {
            self::STATUS_QUARANTINED => '样本隔离观察中，禁止领用',
            self::STATUS_FROZEN => '复检未通过，样本继续冻结，禁止领用',
            self::STATUS_DISCARDED => '样本已废弃，无法领用',
            default => null,
        };
    }

    /**
     * 领用拦截：隔离中、复检未通过冻结、已废弃的样本不得用于后续实验。
     *
     * @throws SampleUsageBlockedException
     */
    public function assertUsable(): void
    {
        if ($reason = $this->blockReason()) {
            throw new SampleUsageBlockedException($this, $reason);
        }
    }

    public function retestOverdue(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();
        return $this->retest_due_at !== null
            && in_array($this->status, [self::STATUS_QUARANTINED, self::STATUS_FROZEN], true)
            && $this->retest_due_at->isBefore($now);
    }

    /** @param Builder<Sample> $query */
    public function scopeSearch(Builder $query, ?string $keyword): void
    {
        if ($keyword) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', "%{$keyword}%")
                ->orWhere('name', 'like', "%{$keyword}%")
                ->orWhere('batch_no', 'like', "%{$keyword}%"));
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageEvent extends Model
{
    use HasFactory;

    protected $fillable = ['sample_id', 'incubator_id', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    /** @return BelongsTo<Incubator, $this> */
    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }

    /**
     * 与给定时间区间的交集；无交集返回 null。
     * ended_at 为 null 表示至今仍在培养箱中。
     */
    public function overlap(\DateTimeInterface $start, \DateTimeInterface $end): ?array
    {
        $otherEnd = $end instanceof \DateTime ? \Carbon\Carbon::instance($end) : \Carbon\Carbon::parse($end);
        $evEnd = $this->ended_at ?? \Carbon\Carbon::now();
        $overlapStart = $this->started_at->greaterThan($start) ? $this->started_at : \Carbon\Carbon::parse($start);
        $overlapEnd = $evEnd->lessThan($otherEnd) ? $evEnd : $otherEnd;

        // 时间点相接不算共同存放，必须严格重叠
        if ($overlapStart->greaterThanOrEqualTo($overlapEnd)) {
            return null;
        }

        return [$overlapStart, $overlapEnd];
    }
}

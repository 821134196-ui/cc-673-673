<?php

namespace App\Services;

use App\Models\ExposureRecord;
use App\Models\Incubator;
use App\Models\IsolationEvent;
use App\Models\Sample;
use App\Models\StorageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 同箱暴露筛选：
 * 唯一依据是 storage_events（样本-培养箱-时间区间）与隔离登记的可疑时间段
 * 是否在同一培养箱内严格重叠。可疑时间段之后才放入的样本 started_at >= 窗口结束，
 * 不会产生交集，因而不会被算作同箱暴露。
 */
class ExposureService
{
    /**
     * 扫描某隔离事件的同箱候选样本（不写库）。
     *
     * @return Collection<int, array{sample: Sample, event: StorageEvent, overlap_start: Carbon, overlap_end: Carbon}>
     */
    public function scan(IsolationEvent $event): Collection
    {
        $windowStart = $event->suspicious_started_at->copy();
        $windowEnd = $event->suspicious_ended_at->copy();

        // SQL 层先粗筛区间相交：started_at < windowEnd AND (ended_at IS NULL OR ended_at > windowStart)
        // 严格不等号保证"窗口结束后才放入"与"窗口开始前已取出"都被排除。
        $events = StorageEvent::with('sample')
            ->where('incubator_id', $event->incubator_id)
            ->where('sample_id', '!=', $event->source_sample_id)
            ->where('started_at', '<', $windowEnd)
            ->where(function ($q) use ($windowStart) {
                $q->whereNull('ended_at')->orWhere('ended_at', '>', $windowStart);
            })
            ->get();

        return $events->map(function (StorageEvent $se) use ($windowStart, $windowEnd) {
            $overlap = $se->overlap($windowStart, $windowEnd);
            if ($overlap === null) {
                return null;
            }
            return [
                'sample' => $se->sample,
                'event' => $se,
                'overlap_start' => $overlap[0],
                'overlap_end' => $overlap[1],
            ];
        })->filter()->values();
    }

    /**
     * 扫描并把候选快照写入 exposure_records（已存在的记录保留，不覆盖负责人判定）。
     *
     * @return Collection<int, ExposureRecord>
     */
    public function syncRecords(IsolationEvent $event): Collection
    {
        return DB::transaction(function () use ($event) {
            $existing = $event->exposureRecords()->pluck('sample_id')->all();

            return $this->scan($event)->map(function ($row) use ($event, $existing) {
                if (in_array($row['sample']->id, $existing, true)) {
                    return $event->exposureRecords()->where('sample_id', $row['sample']->id)->first();
                }

                return ExposureRecord::create([
                    'isolation_event_id' => $event->id,
                    'sample_id' => $row['sample']->id,
                    'overlap_started_at' => $row['overlap_start'],
                    'overlap_ended_at' => $row['overlap_end'],
                    'decision' => ExposureRecord::DECISION_PENDING,
                ]);
            });
        });
    }
}

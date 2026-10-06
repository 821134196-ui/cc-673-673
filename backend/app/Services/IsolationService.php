<?php

namespace App\Services;

use App\Models\IsolationEvent;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IsolationService
{
    public function __construct(private readonly ExposureService $exposureService)
    {
    }

    /**
     * 实验员登记隔离：来源样本立即隔离，按可疑时间段扫描同箱候选。
     *
     * @param array{
     *     source_sample_id: int,
     *     incubator_id: int,
     *     suspicious_started_at: string,
     *     suspicious_ended_at: string,
     *     reason?: ?string,
     *     retest_due_at?: ?string
     * } $data
     */
    public function register(array $data, User $operator): IsolationEvent
    {
        return DB::transaction(function () use ($data, $operator) {
            $event = IsolationEvent::create([
                'source_sample_id' => $data['source_sample_id'],
                'incubator_id' => $data['incubator_id'],
                'suspicious_started_at' => $data['suspicious_started_at'],
                'suspicious_ended_at' => $data['suspicious_ended_at'],
                'reason' => $data['reason'] ?? null,
                'status' => IsolationEvent::STATUS_OPEN,
                'registered_by' => $operator->id,
            ]);

            $source = Sample::lockForUpdate()->findOrFail($data['source_sample_id']);
            if ($source->status !== Sample::STATUS_DISCARDED) {
                $source->update([
                    'status' => Sample::STATUS_QUARANTINED,
                    'quarantined_at' => Carbon::now(),
                    'retest_due_at' => $data['retest_due_at']
                        ?? Carbon::now()->addDays(config('lab.retest_days', 7)),
                ]);
            }

            $this->exposureService->syncRecords($event);

            return $event->load(['sourceSample', 'incubator', 'exposureRecords.sample']);
        });
    }
}

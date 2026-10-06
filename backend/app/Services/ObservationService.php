<?php

namespace App\Services;

use App\Models\ObservationRecord;
use App\Models\Quarantine;
use App\Models\Sample;
use App\Models\UsageRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ObservationService
{
    /**
     * 日常观察记录（不改变隔离状态；任何状态均可追加观察）。
     */
    public function addObservation(Sample $sample, array $data, int $operatorId): ObservationRecord
    {
        return ObservationRecord::create([
            'sample_id' => $sample->id,
            'type' => ObservationRecord::TYPE_OBSERVATION,
            'content' => $data['content'] ?? null,
            'operator_id' => $operatorId,
        ]);
    }

    /**
     * 复检结论（每个样本独立记录、独立生效）：
     * - passed：解除当前隔离，样本恢复可领用；
     * - failed：继续冻结，状态置为 frozen，复检截止日顺延。
     */
    public function recordRecheck(Sample $sample, string $result, array $data, int $operatorId): ObservationRecord
    {
        if (!in_array($result, [ObservationRecord::RESULT_PASSED, ObservationRecord::RESULT_FAILED], true)) {
            throw ValidationException::withMessages(['result' => '复检结果只能是 passed 或 failed']);
        }

        return DB::transaction(function () use ($sample, $result, $data, $operatorId) {
            $record = ObservationRecord::create([
                'sample_id' => $sample->id,
                'type' => ObservationRecord::TYPE_RECHECK,
                'result' => $result,
                'content' => $data['content'] ?? null,
                'operator_id' => $operatorId,
            ]);

            if ($result === ObservationRecord::RESULT_PASSED) {
                $active = Quarantine::where('sample_id', $sample->id)->whereNull('ended_at')->first();
                if ($active) {
                    $active->update([
                        'ended_at' => Carbon::now(),
                        'ended_by' => $operatorId,
                        'release_note' => '复检通过，解除隔离',
                    ]);
                }
                $sample->update([
                    'status' => Sample::STATUS_RELEASED,
                    'released_at' => Carbon::now(),
                    'recheck_deadline' => null,
                ]);
            } else {
                // 复检未通过：继续冻结，给出新的复检截止日
                $nextDeadline = !empty($data['next_recheck_deadline'])
                    ? Carbon::parse($data['next_recheck_deadline'])->toDateString()
                    : Carbon::tomorrow()->addDays(6)->toDateString();
                $sample->update([
                    'status' => Sample::STATUS_FROZEN,
                    'recheck_deadline' => $nextDeadline,
                ]);
            }

            return $record;
        });
    }

    /**
     * 废弃登记：记录处理方式与执行人；原批次号与全部观察历史保留不删。
     */
    public function discard(Sample $sample, array $data, int $operatorId): ObservationRecord
    {
        if (empty($data['disposal_method'])) {
            throw ValidationException::withMessages(['disposal_method' => '废弃时必须记录处理方式']);
        }
        if (empty($data['executed_by_name']) && empty($data['executed_by_user_id'])) {
            throw ValidationException::withMessages(['executed_by_name' => '废弃时必须记录执行人']);
        }

        return DB::transaction(function () use ($sample, $data, $operatorId) {
            $now = Carbon::now();

            $record = ObservationRecord::create([
                'sample_id' => $sample->id,
                'type' => ObservationRecord::TYPE_DISPOSAL,
                'content' => $data['content'] ?? null,
                'disposal_method' => $data['disposal_method'],
                'executed_by_name' => $data['executed_by_name'] ?? null,
                'executed_by_user_id' => $data['executed_by_user_id'] ?? $operatorId,
                'executed_at' => $now,
                'operator_id' => $operatorId,
            ]);

            // 结束进行中的隔离期
            Quarantine::where('sample_id', $sample->id)
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => $now,
                    'ended_by' => $operatorId,
                    'release_note' => '样本废弃',
                ]);

            // 仅置状态；样本记录、batch_no、观察历史均保留
            $sample->update([
                'status' => Sample::STATUS_DISCARDED,
                'released_at' => $now,
                'recheck_deadline' => null,
            ]);

            return $record;
        });
    }

    /**
     * 领用申请：隔离期间 / 复检未通过冻结 / 已废弃一律拦截。
     * 拦截与放行都落库留痕。
     */
    public function requestUsage(Sample $sample, string $experimentName, int $userId): UsageRequest
    {
        $blocked = $sample->isUsageBlocked();

        return UsageRequest::create([
            'sample_id' => $sample->id,
            'experiment_name' => $experimentName,
            'status' => $blocked ? UsageRequest::STATUS_BLOCKED : UsageRequest::STATUS_APPROVED,
            'block_reason' => $blocked ? $sample->blockReason() : null,
            'requested_by' => $userId,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Observation;
use App\Models\Sample;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 样本结论：每个样本独立登记、互不影响。
 * - observation 观察记录：状态不变，可更新复检截止日
 * - retest_pass 复检通过：放行，之后可领用
 * - retest_fail 复检未通过：继续冻结，必须给出新的复检截止日
 * - discard 废弃：记录处理方式与执行人；原批次号与全部观察历史保留
 */
class ConclusionService
{
    /**
     * @param array{
     *     type: string,
     *     notes?: ?string,
     *     retest_due_at?: ?string,
     *     disposal_method?: ?string,
     *     disposed_by?: ?string
     * } $data
     */
    public function record(Sample $sample, array $data, User $operator): Observation
    {
        return DB::transaction(function () use ($sample, $data, $operator) {
            $sample = Sample::lockForUpdate()->findOrFail($sample->id);

            if ($sample->status === Sample::STATUS_DISCARDED) {
                throw ValidationException::withMessages([
                    'sample_id' => '样本已废弃，不能再登记结论',
                ]);
            }

            $observation = new Observation([
                'sample_id' => $sample->id,
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
                'operator_id' => $operator->id,
            ]);

            switch ($data['type']) {
                case Observation::TYPE_OBSERVATION:
                    if (! empty($data['retest_due_at'])) {
                        $observation->retest_due_at = $data['retest_due_at'];
                        $sample->retest_due_at = $data['retest_due_at'];
                        $sample->save();
                    }
                    break;

                case Observation::TYPE_RETEST_PASS:
                    $sample->update([
                        'status' => Sample::STATUS_RELEASED,
                        'retest_due_at' => null,
                    ]);
                    break;

                case Observation::TYPE_RETEST_FAIL:
                    if (empty($data['retest_due_at'])) {
                        throw ValidationException::withMessages([
                            'retest_due_at' => '复检未通过时必须指定下一次复检截止日',
                        ]);
                    }
                    $observation->retest_due_at = $data['retest_due_at'];
                    $sample->update([
                        'status' => Sample::STATUS_FROZEN,
                        'retest_due_at' => $data['retest_due_at'],
                    ]);
                    break;

                case Observation::TYPE_DISCARD:
                    if (empty($data['disposal_method']) || empty($data['disposed_by'])) {
                        throw ValidationException::withMessages([
                            'disposal' => '废弃时必须填写处理方式与执行人',
                        ]);
                    }
                    $observation->disposal_method = $data['disposal_method'];
                    $observation->disposed_by = $data['disposed_by'];
                    $sample->update([
                        'status' => Sample::STATUS_DISCARDED,
                        'retest_due_at' => null,
                    ]);
                    break;

                default:
                    throw ValidationException::withMessages(['type' => '未知结论类型']);
            }

            $observation->save();

            return $observation->load('photos');
        });
    }
}

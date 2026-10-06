<?php

namespace App\Services;

use App\Models\ContaminationReport;
use App\Models\Exposure;
use App\Models\Quarantine;
use App\Models\Sample;
use App\Models\StorageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContaminationService
{
    private function generateReportNo(): string
    {
        return 'CR-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
    }

    /**
     * 实验员登记疑似污染：登记培养箱与可疑时间段，可疑样本立即隔离。
     */
    public function registerReport(array $data, int $userId): ContaminationReport
    {
        return DB::transaction(function () use ($data, $userId) {
            $start = Carbon::parse($data['suspected_start_at']);
            $end = Carbon::parse($data['suspected_end_at']);
            if ($end->lte($start)) {
                throw ValidationException::withMessages([
                    'suspected_end_at' => '可疑时间段结束时间必须晚于开始时间',
                ]);
            }

            $report = ContaminationReport::create([
                'report_no' => $data['report_no'] ?? $this->generateReportNo(),
                'incubator_id' => $data['incubator_id'],
                'suspect_sample_id' => $data['suspect_sample_id'] ?? null,
                'suspected_start_at' => $start,
                'suspected_end_at' => $end,
                'description' => $data['description'] ?? null,
                'status' => ContaminationReport::STATUS_OPEN,
                'reported_by' => $userId,
            ]);

            // 发现疑似污染时，先隔离可疑样本本身
            if ($report->suspect_sample_id) {
                $suspect = Sample::findOrFail($report->suspect_sample_id);
                $deadline = isset($data['recheck_deadline'])
                    ? Carbon::parse($data['recheck_deadline'])->toDateString()
                    : Carbon::tomorrow()->addDays(6)->toDateString(); // 默认 7 天内复检
                $this->quarantine($suspect, $report, $deadline, $userId, '疑似污染，登记即隔离');
            }

            return $report->load(['incubator', 'suspectSample', 'reporter']);
        });
    }

    /**
     * 同箱暴露筛选（核心）：
     * 找出在可疑时间段内，与可疑样本实际共同存放在同一培养箱的培养皿。
     *
     * 区间重叠判定（半开区间）：
     *   入箱时间 stored_at < 可疑结束 E
     *   且 (未出箱 removed_at IS NULL 或 出箱时间 removed_at > 可疑开始 S)
     *
     * 后来才放入（stored_at >= E）或可疑时间段开始前已取出（removed_at <= S）的样本不算暴露。
     *
     * @return array{present: \Illuminate\Support\Collection, checked: int}
     */
    public function scanExposures(ContaminationReport $report): array
    {
        return DB::transaction(function () use ($report) {
            $windowStart = $report->suspected_start_at->copy();
            $windowEnd = $report->suspected_end_at->copy();

            // 该培养箱内出现过的所有存放记录（排除可疑样本本身）
            $records = StorageRecord::with('sample')
                ->where('incubator_id', $report->incubator_id)
                ->when($report->suspect_sample_id, fn ($q) => $q->where('sample_id', '!=', $report->suspect_sample_id))
                ->orderBy('stored_at')
                ->get();

            $present = collect();
            $checked = 0;

            // 按样本汇总：同一样本在该箱可能有多段存放记录
            $bySample = $records->groupBy('sample_id');

            foreach ($bySample as $sampleId => $sampleRecords) {
                $checked++;
                $hits = [];
                foreach ($sampleRecords as $record) {
                    $intervalStart = $record->stored_at->copy();
                    $intervalEnd = $record->removed_at ? $record->removed_at->copy() : Carbon::now();

                    // 区间重叠（半开区间）：stored_at < E 且 (仍在箱内 OR removed_at > S)
                    // 后来才放入（stored_at >= E）或可疑开始前已出箱（removed_at <= S）均不命中
                    $isOverlap = $intervalStart->lessThan($windowEnd)
                        && (!$record->removed_at || $record->removed_at->greaterThan($windowStart));

                    if ($isOverlap) {
                        $overlapStart = $intervalStart->greaterThan($windowStart) ? $intervalStart->copy() : $windowStart->copy();
                        $overlapEnd = $intervalEnd->lessThan($windowEnd) ? $intervalEnd->copy() : $windowEnd->copy();
                        $hits[] = [
                            'storage_record_id' => $record->id,
                            'stored_at' => $record->stored_at->toDateTimeString(),
                            'removed_at' => $record->removed_at?->toDateTimeString(),
                            'overlap_start' => $overlapStart->toDateTimeString(),
                            'overlap_end' => $overlapEnd->toDateTimeString(),
                        ];
                    }
                }

                $wasPresent = count($hits) > 0;
                $overlapStart = $wasPresent ? collect($hits)->min('overlap_start') : null;
                $overlapEnd = $wasPresent ? collect($hits)->max('overlap_end') : null;

                $exposure = Exposure::updateOrCreate(
                    [
                        'contamination_report_id' => $report->id,
                        'sample_id' => $sampleId,
                    ],
                    [
                        'was_present' => $wasPresent,
                        'overlap_start' => $overlapStart,
                        'overlap_end' => $overlapEnd,
                        'evidence' => $hits,
                        'decision' => Exposure::DECISION_PENDING,
                        'decision_note' => null,
                        'decided_by' => null,
                        'decided_at' => null,
                    ]
                );

                if ($wasPresent) {
                    $present->push($exposure->load('sample'));
                }
            }

            return ['present' => $present, 'checked' => $checked];
        });
    }

    /**
     * 负责人逐样本判定：受影响 → 隔离该样本；不受影响 → 标记排除。
     * 不同样本的结论互相独立，各自记录。
     */
    public function decideExposure(Exposure $exposure, string $decision, int $supervisorId, ?string $note = null, ?string $recheckDeadline = null): Exposure
    {
        if (!in_array($decision, [Exposure::DECISION_EXPOSED, Exposure::DECISION_CLEARED], true)) {
            throw ValidationException::withMessages(['decision' => '结论只能是 exposed 或 cleared']);
        }

        return DB::transaction(function () use ($exposure, $decision, $supervisorId, $note, $recheckDeadline) {
            $exposure->update([
                'decision' => $decision,
                'decision_note' => $note,
                'decided_by' => $supervisorId,
                'decided_at' => Carbon::now(),
            ]);

            if ($decision === Exposure::DECISION_EXPOSED) {
                $deadline = $recheckDeadline
                    ? Carbon::parse($recheckDeadline)->toDateString()
                    : Carbon::tomorrow()->addDays(6)->toDateString();
                $exposure->update(['recheck_deadline' => $deadline]);

                $sample = $exposure->sample;
                $this->quarantine(
                    $sample,
                    $exposure->report,
                    $deadline,
                    $supervisorId,
                    '负责人判定同箱暴露，予以隔离'
                );
            }

            return $exposure->fresh(['sample', 'decider', 'report']);
        });
    }

    /**
     * 将样本置入隔离（幂等：已在隔离期内则只更新复检截止日，取最早日期）。
     */
    public function quarantine(Sample $sample, ?ContaminationReport $report, string $deadline, int $operatorId, string $note = ''): Quarantine
    {
        return DB::transaction(function () use ($sample, $report, $deadline, $operatorId, $note) {
            if ($sample->status === Sample::STATUS_DISCARDED) {
                throw ValidationException::withMessages(['sample_id' => '样本已废弃，不能再隔离']);
            }

            $active = Quarantine::where('sample_id', $sample->id)->whereNull('ended_at')->first();

            if (!$active) {
                $active = Quarantine::create([
                    'sample_id' => $sample->id,
                    'contamination_report_id' => $report?->id,
                    'started_at' => Carbon::now(),
                    'started_by' => $operatorId,
                ]);
            }

            // 复检截止日取最早，避免多次事件互相推迟
            $currentDeadline = $sample->recheck_deadline?->toDateString();
            $finalDeadline = $currentDeadline && $currentDeadline < $deadline ? $currentDeadline : $deadline;

            $sample->update([
                'status' => Sample::STATUS_QUARANTINED,
                'quarantined_at' => $sample->quarantined_at ?? Carbon::now(),
                'released_at' => null,
                'recheck_deadline' => $finalDeadline,
            ]);

            return $active;
        });
    }
}

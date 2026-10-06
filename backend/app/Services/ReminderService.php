<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\Sample;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReminderService
{
    /**
     * 本地模拟提醒：扫描仍处隔离/冻结且复检截止日为今天或已逾期的样本，
     * 在本地 reminders 表生成提醒（不外发、不接第三方）。
     * 同一天同样本同类型只生成一次（dedupe_key 唯一）。
     *
     * @return \Illuminate\Support\Collection<int, Reminder>
     */
    public function generateDueReminders(?Carbon $today = null): Collection
    {
        $today = $today ? $today->copy()->startOfDay() : Carbon::today();
        $created = collect();

        $samples = Sample::whereIn('status', [Sample::STATUS_QUARANTINED, Sample::STATUS_FROZEN])
            ->whereNotNull('recheck_deadline')
            ->get();

        foreach ($samples as $sample) {
            $deadline = $sample->recheck_deadline->copy()->startOfDay();
            $type = null;
            $title = null;
            $body = null;

            if ($deadline->equalTo($today)) {
                $type = Reminder::TYPE_RECHECK_DUE;
                $title = "样本 {$sample->code} 今日复检截止";
                $body = "样本（批次 {$sample->batch_no}）复检截止日为 {$deadline->toDateString()}，请及时安排复检。";
            } elseif ($deadline->lessThan($today)) {
                $type = Reminder::TYPE_RECHECK_OVERDUE;
                $days = $deadline->diffInDays($today);
                $title = "样本 {$sample->code} 复检已逾期 {$days} 天";
                $body = "样本（批次 {$sample->batch_no}）复检截止日 {$deadline->toDateString()} 已过，仍未解除冻结。";
            }

            if (!$type) {
                continue;
            }

            $dedupeKey = "{$sample->id}:{$type}:{$today->toDateString()}";
            $reminder = Reminder::firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'sample_id' => $sample->id,
                    'type' => $type,
                    'channel' => 'local',
                    'title' => $title,
                    'body' => $body,
                    'remind_date' => $today->toDateString(),
                    'sent_at' => Carbon::now(), // 本地模拟“已发送”
                ]
            );

            if ($reminder->wasRecentlyCreated) {
                $created->push($reminder);
            }
        }

        return $created;
    }
}

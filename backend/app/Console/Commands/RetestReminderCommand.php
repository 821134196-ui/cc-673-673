<?php

namespace App\Console\Commands;

use App\Models\Sample;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * 本地模拟提醒：列出临近/超过复检截止日的样本。
 * 可挂到调度：app/Console/Kernel 或 routes/console.php 中 schedule()。
 */
class RetestReminderCommand extends Command
{
    protected $signature = 'lab:retest-remind {--days= : 未来多少天内到期也提醒}';

    protected $description = '输出复检截止日临近/逾期的隔离与冻结样本（本地模拟提醒）';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('lab.remind_before_days', 2));
        $horizon = Carbon::now()->addDays($days);

        $samples = Sample::whereIn('status', [Sample::STATUS_QUARANTINED, Sample::STATUS_FROZEN])
            ->whereNotNull('retest_due_at')
            ->where('retest_due_at', '<=', $horizon)
            ->orderBy('retest_due_at')
            ->get();

        if ($samples->isEmpty()) {
            $this->info('没有临近复检截止日的样本。');

            return self::SUCCESS;
        }

        $rows = $samples->map(function (Sample $s) {
            $overdue = $s->retestOverdue();
            return [
                $s->code,
                $s->status_label,
                $s->batch_no,
                $s->retest_due_at->toDateTimeString(),
                $overdue ? '已逾期' : '临近',
            ];
        })->all();

        $this->table(['样本编号', '状态', '批次', '复检截止日', '提醒'], $rows);
        $this->line(sprintf('（本地模拟提醒，共 %d 条；对应接口 GET /api/reminders）', $samples->count()));

        return self::SUCCESS;
    }
}

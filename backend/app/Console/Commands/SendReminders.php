<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send {--date= : 指定模拟日期 Y-m-d，默认今天}';

    protected $description = '本地模拟发送复检截止/逾期提醒（写入本地 reminders 表，不外发）';

    public function handle(ReminderService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : null;
        $created = $service->generateDueReminders($date);

        if ($created->isEmpty()) {
            $this->info('没有需要提醒的样本。');
        } else {
            foreach ($created as $reminder) {
                $this->line("[本地模拟] {$reminder->title} -> {$reminder->body}");
            }
            $this->info("共生成 {$created->count()} 条本地提醒。");
        }

        return self::SUCCESS;
    }
}

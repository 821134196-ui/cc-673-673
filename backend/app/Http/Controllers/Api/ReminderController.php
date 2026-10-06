<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use Illuminate\Support\Carbon;

class ReminderController extends Controller
{
    /**
     * 提醒接口（本地模拟）：返回临近复检截止日 / 已逾期的隔离或冻结样本。
     */
    public function index()
    {
        $days = config('lab.remind_before_days', 2);
        $horizon = Carbon::now()->addDays($days);

        $samples = Sample::whereIn('status', [Sample::STATUS_QUARANTINED, Sample::STATUS_FROZEN])
            ->whereNotNull('retest_due_at')
            ->where('retest_due_at', '<=', $horizon)
            ->orderBy('retest_due_at')
            ->get()
            ->append(['status_label'])
            ->map(fn (Sample $s) => [
                'sample_id' => $s->id,
                'code' => $s->code,
                'status' => $s->status,
                'status_label' => $s->status_label,
                'retest_due_at' => $s->retest_due_at->toIso8601String(),
                'overdue' => $s->retestOverdue(),
                'days_left' => (int) Carbon::now()->startOfDay()->diffInDays($s->retest_due_at->copy()->startOfDay(), false),
                'message' => $s->retestOverdue()
                    ? "样本 {$s->code} 复检已逾期，请尽快处理"
                    : "样本 {$s->code} 复检截止日临近（{$s->retest_due_at->toDateString()}）",
            ]);

        return response()->json(['data' => $samples]);
    }
}

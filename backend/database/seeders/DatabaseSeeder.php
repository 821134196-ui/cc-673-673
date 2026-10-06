<?php

namespace Database\Seeders;

use App\Models\Incubator;
use App\Models\IsolationEvent;
use App\Models\Sample;
use App\Models\StorageEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tech = User::firstOrCreate(
            ['email' => 'tech@lab.local'],
            ['name' => '李实验（实验员）', 'password' => 'password', 'role' => User::ROLE_TECHNICIAN],
        );
        $manager = User::firstOrCreate(
            ['email' => 'manager@lab.local'],
            ['name' => '王负责（负责人）', 'password' => 'password', 'role' => User::ROLE_MANAGER],
        );

        $inc1 = Incubator::firstOrCreate(
            ['code' => 'INC-A1'],
            ['name' => 'A1 号培养箱', 'location' => '洁净间 1'],
        );
        $inc2 = Incubator::firstOrCreate(
            ['code' => 'INC-B2'],
            ['name' => 'B2 号培养箱', 'location' => '洁净间 2'],
        );

        // P-001 为疑似污染源；P-002 在可疑时间段内同箱；P-003 窗口结束后才放入（不应被筛出）；
        // P-004 窗口开始前已取出（不应被筛出）；P-005 从未在该培养箱（不应被筛出）。
        $samples = collect([
            ['code' => 'P-001', 'batch_no' => 'B20261001', 'name' => '疑似污染样本'],
            ['code' => 'P-002', 'batch_no' => 'B20261001', 'name' => '同箱样本-在窗口内'],
            ['code' => 'P-003', 'batch_no' => 'B20261002', 'name' => '窗口结束后才放入'],
            ['code' => 'P-004', 'batch_no' => 'B20260930', 'name' => '窗口开始前已取出'],
            ['code' => 'P-005', 'batch_no' => 'B20261002', 'name' => '其他培养箱样本'],
        ])->map(fn ($s) => Sample::firstOrCreate(['code' => $s['code']], $s));

        StorageEvent::truncate();
        $se = fn (Sample $s, Incubator $inc, string $start, ?string $end) => StorageEvent::create([
            'sample_id' => $s->id,
            'incubator_id' => $inc->id,
            'started_at' => Carbon::parse($start),
            'ended_at' => $end ? Carbon::parse($end) : null,
        ]);

        // 可疑时间段：2026-10-04 00:00 ~ 2026-10-05 00:00
        $se($samples[0], $inc1, '2026-10-03 08:00', null);                 // 来源样本，至今在箱
        $se($samples[1], $inc1, '2026-10-04 12:00', '2026-10-04 20:00');  // 与窗口重叠 ✓
        $se($samples[2], $inc1, '2026-10-05 09:00', null);                // 窗口结束后才放入 ✗
        $se($samples[3], $inc1, '2026-10-02 08:00', '2026-10-03 20:00');  // 窗口前已取出 ✗
        $se($samples[4], $inc2, '2026-10-03 08:00', null);                // 其他培养箱 ✗

        $event = IsolationEvent::firstOrCreate(
            ['source_sample_id' => $samples[0]->id, 'incubator_id' => $inc1->id],
            [
                'suspicious_started_at' => Carbon::parse('2026-10-04 00:00'),
                'suspicious_ended_at' => Carbon::parse('2026-10-05 00:00'),
                'reason' => '观察到培养基表面出现异常菌落',
                'registered_by' => $tech->id,
            ],
        );

        app(\App\Services\ExposureService::class)->syncRecords($event);

        $samples[0]->update([
            'status' => Sample::STATUS_QUARANTINED,
            'quarantined_at' => Carbon::now(),
            'retest_due_at' => Carbon::now()->addDay(),
        ]);
    }
}

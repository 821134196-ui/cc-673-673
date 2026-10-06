<?php

namespace Database\Seeders;

use App\Models\Incubator;
use App\Models\Sample;
use App\Models\StorageRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $technician = User::updateOrCreate(
            ['email' => 'tech@lab.local'],
            [
                'name' => '实验员小李',
                'display_name' => '小李（实验员）',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TECHNICIAN,
            ]
        );

        $supervisor = User::updateOrCreate(
            ['email' => 'lead@lab.local'],
            [
                'name' => '负责人王老师',
                'display_name' => '王老师（负责人）',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPERVISOR,
            ]
        );

        $incubator = Incubator::updateOrCreate(
            ['code' => 'INC-A'],
            ['name' => 'A 号培养箱', 'location' => '洁净室 201']
        );

        $samples = [];
        foreach (['P-001', 'P-002', 'P-003', 'P-004'] as $i => $code) {
            $samples[$code] = Sample::updateOrCreate(
                ['code' => $code],
                [
                    'name' => "培养皿 {$code}",
                    'batch_no' => 'B20261001',
                    'description' => '演示数据',
                    'status' => Sample::STATUS_NORMAL,
                    'registered_by' => $technician->id,
                ]
            );
        }

        // 存放时间线（模拟同箱与“后来才放入”）
        // P-001: 10-01 08:00 入箱，仍在箱 —— 可疑样本
        // P-002: 10-02 09:00 ~ 10-04 18:00 —— 可疑窗口内同箱，应命中
        // P-003: 09-25 09:00 ~ 09-30 18:00 —— 窗口前已取出，不应命中
        // P-004: 10-06 10:00 入箱（可疑窗口结束后才放入）—— 不应命中
        $timeline = [
            ['P-001', '2026-10-01 08:00:00', null],
            ['P-002', '2026-10-02 09:00:00', '2026-10-04 18:00:00'],
            ['P-003', '2026-09-25 09:00:00', '2026-09-30 18:00:00'],
            ['P-004', '2026-10-06 10:00:00', null],
        ];

        foreach ($timeline as [$code, $storedAt, $removedAt]) {
            StorageRecord::updateOrCreate(
                [
                    'sample_id' => $samples[$code]->id,
                    'incubator_id' => $incubator->id,
                    'stored_at' => $storedAt,
                ],
                [
                    'removed_at' => $removedAt,
                    'registered_by' => $technician->id,
                ]
            );
        }
    }
}

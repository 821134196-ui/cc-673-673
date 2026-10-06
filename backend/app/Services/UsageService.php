<?php

namespace App\Services;

use App\Models\Sample;
use App\Models\SampleUsage;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * 领用：隔离中 / 复检未通过冻结 / 已废弃的样本一律拦截。
 */
class UsageService
{
    public function checkout(Sample $sample, string $experimentName, User $operator): SampleUsage
    {
        // assertUsable 抛 SampleUsageBlockedException
        $sample->assertUsable();

        return SampleUsage::create([
            'sample_id' => $sample->id,
            'experiment_name' => $experimentName,
            'used_by' => $operator->id,
            'used_at' => Carbon::now(),
        ]);
    }
}

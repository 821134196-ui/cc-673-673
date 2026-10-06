<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Services\UsageService;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    /**
     * 领用到后续实验。隔离中 / 冻结 / 已废弃的样本在此被拦截。
     */
    public function checkout(Request $request, Sample $sample)
    {
        $data = $request->validate([
            'experiment_name' => ['required', 'string', 'max:128'],
        ]);

        $usage = app(UsageService::class)->checkout($sample, $data['experiment_name'], $request->user());

        return response()->json(['data' => $usage], 201);
    }
}

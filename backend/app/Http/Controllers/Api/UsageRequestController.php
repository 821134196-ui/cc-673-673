<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Models\UsageRequest;
use App\Services\ObservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageRequestController extends Controller
{
    /**
     * 领用申请：隔离/冻结/废弃样本在此被拦截。
     */
    public function store(Request $request, Sample $sample, ObservationService $service): JsonResponse
    {
        $data = $request->validate([
            'experiment_name' => ['required', 'string', 'max:200'],
        ]);

        $usage = $service->requestUsage($sample, $data['experiment_name'], $request->user()->id);

        // 被拦截返回 423 Locked，语义化提示；前端明确展示拦截原因
        $status = $usage->status === UsageRequest::STATUS_BLOCKED ? 423 : 201;

        return response()->json([
            ...$usage->toArray(),
            'sample_status' => $sample->fresh()->status,
        ], $status);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            UsageRequest::with(['sample', 'requester'])->orderByDesc('id')->limit(200)->get()
        );
    }
}

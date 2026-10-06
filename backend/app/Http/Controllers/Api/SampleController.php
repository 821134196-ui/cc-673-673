<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SampleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sample::query()
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('batch_no')->toString(), fn ($q, $v) => $q->where('batch_no', 'like', "%{$v}%"))
            ->when($request->string('q')->toString(), function ($q, $v) {
                $q->where(fn ($qq) => $qq->where('code', 'like', "%{$v}%")
                    ->orWhere('name', 'like', "%{$v}%"));
            });

        return response()->json($query->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('samples', 'code')],
            'name' => ['nullable', 'string', 'max:100'],
            'batch_no' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $sample = Sample::create([
            ...$data,
            'status' => Sample::STATUS_NORMAL,
            'registered_by' => $request->user()->id,
        ]);

        return response()->json($sample, 201);
    }

    public function show(Sample $sample): JsonResponse
    {
        $sample->load([
            'storageRecords.incubator',
            'observations.photos',
            'observations.executor',
            'photos',
            'quarantines',
        ]);

        // 当前是否处于领用冻结状态及原因，前端观察页面直接使用
        return response()->json([
            ...$sample->toArray(),
            'status_label' => $sample->statusLabel(),
            'usage_blocked' => $sample->isUsageBlocked(),
            'block_reason' => $sample->blockReason(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use App\Services\ObservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObservationController extends Controller
{
    /**
     * 日常观察（每样本独立追加，不影响隔离状态）。
     */
    public function store(Request $request, Sample $sample, ObservationService $service): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $record = $service->addObservation($sample, $data, $request->user()->id);

        return response()->json($record->load('photos'), 201);
    }

    /**
     * 复检结论：passed 解除隔离；failed 继续冻结。
     */
    public function recheck(Request $request, Sample $sample, ObservationService $service): JsonResponse
    {
        $data = $request->validate([
            'result' => ['required', 'in:passed,failed'],
            'content' => ['nullable', 'string', 'max:2000'],
            'next_recheck_deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $record = $service->recordRecheck($sample, $data['result'], $data, $request->user()->id);

        return response()->json([
            'record' => $record,
            'sample' => $sample->fresh(),
        ]);
    }

    /**
     * 废弃：记录处理方式与执行人，原批次与观察历史保留。
     */
    public function discard(Request $request, Sample $sample, ObservationService $service): JsonResponse
    {
        $data = $request->validate([
            'disposal_method' => ['required', 'string', 'max:200'],
            'executed_by_name' => ['nullable', 'string', 'max:100'],
            'content' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = $service->discard($sample, $data, $request->user()->id);

        return response()->json([
            'record' => $record->load('executor'),
            'sample' => $sample->fresh(),
        ]);
    }
}

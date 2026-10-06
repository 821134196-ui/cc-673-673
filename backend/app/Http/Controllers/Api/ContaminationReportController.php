<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContaminationReport;
use App\Models\Exposure;
use App\Services\ContaminationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContaminationReportController extends Controller
{
    /**
     * 实验员登记疑似污染：样本 + 培养箱位置 + 可疑时间段。登记即隔离可疑样本。
     */
    public function store(Request $request, ContaminationService $service): JsonResponse
    {
        $data = $request->validate([
            'incubator_id' => ['required', 'exists:incubators,id'],
            'suspect_sample_id' => ['required', 'exists:samples,id'],
            'suspected_start_at' => ['required', 'date'],
            'suspected_end_at' => ['required', 'date', 'after:suspected_start_at'],
            'recheck_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = $service->registerReport($data, $request->user()->id);

        return response()->json($report, 201);
    }

    public function index(): JsonResponse
    {
        return response()->json(
            ContaminationReport::with(['incubator', 'suspectSample', 'reporter', 'reviewer'])
                ->orderByDesc('id')
                ->get()
        );
    }

    public function show(ContaminationReport $contaminationReport): JsonResponse
    {
        $contaminationReport->load([
            'incubator', 'suspectSample', 'reporter', 'reviewer',
            'exposures.sample', 'exposures.decider',
        ]);

        return response()->json($contaminationReport);
    }

    /**
     * 执行同箱暴露筛选（依据实际存放时间区间）。
     */
    public function scan(ContaminationReport $contaminationReport, ContaminationService $service): JsonResponse
    {
        $result = $service->scanExposures($contaminationReport);

        return response()->json([
            'checked' => $result['checked'],
            'present_count' => $result['present']->count(),
            'present' => $result['present']->values(),
        ]);
    }

    /**
     * 负责人对单个候选样本下独立结论：exposed=受影响并隔离 / cleared=排除。
     */
    public function decideExposure(Request $request, Exposure $exposure, ContaminationService $service): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:exposed,cleared'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
            'recheck_deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $updated = $service->decideExposure(
            $exposure,
            $data['decision'],
            $request->user()->id,
            $data['decision_note'] ?? null,
            $data['recheck_deadline'] ?? null
        );

        return response()->json($updated);
    }

    /**
     * 负责人完成整单判定。
     */
    public function markReviewed(Request $request, ContaminationReport $contaminationReport): JsonResponse
    {
        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $contaminationReport->update([
            'status' => ContaminationReport::STATUS_REVIEWED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        return response()->json($contaminationReport->load(['incubator', 'suspectSample', 'reviewer']));
    }
}

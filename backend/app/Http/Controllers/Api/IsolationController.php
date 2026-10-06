<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExposureRecord;
use App\Models\IsolationEvent;
use App\Services\ExposureService;
use App\Services\IsolationService;
use Illuminate\Http\Request;

class IsolationController extends Controller
{
    public function __construct(
        private readonly IsolationService $isolations,
        private readonly ExposureService $exposures,
    ) {
    }

    public function index()
    {
        $events = IsolationEvent::with(['sourceSample', 'incubator', 'registrant'])
            ->withCount('exposureRecords')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $events]);
    }

    /**
     * 实验员登记样本、培养箱位置和可疑时间段（触发同箱筛选）。
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'source_sample_id' => ['required', 'integer', 'exists:samples,id'],
            'incubator_id' => ['required', 'integer', 'exists:incubators,id'],
            'suspicious_started_at' => ['required', 'date'],
            'suspicious_ended_at' => ['required', 'date', 'after:suspicious_started_at'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'retest_due_at' => ['nullable', 'date', 'after:now'],
        ]);

        $event = $this->isolations->register($data, $request->user());

        return response()->json(['data' => $this->format($event)], 201);
    }

    public function show(IsolationEvent $isolation)
    {
        $isolation->load([
            'sourceSample', 'incubator', 'registrant',
            'exposureRecords.sample.storageEvents',
        ]);

        return response()->json(['data' => $this->format($isolation)]);
    }

    /** 重新扫描（例如补充了新的存放记录后）。 */
    public function rescan(IsolationEvent $isolation)
    {
        $this->exposures->syncRecords($isolation);

        return $this->show($isolation);
    }

    private function format(IsolationEvent $e): array
    {
        return [
            'id' => $e->id,
            'source_sample' => ['id' => $e->sourceSample->id, 'code' => $e->sourceSample->code,
                'status' => $e->sourceSample->status,
                'status_label' => $e->sourceSample->status_label],
            'incubator' => ['id' => $e->incubator->id, 'code' => $e->incubator->code, 'name' => $e->incubator->name],
            'suspicious_started_at' => $e->suspicious_started_at->toIso8601String(),
            'suspicious_ended_at' => $e->suspicious_ended_at->toIso8601String(),
            'reason' => $e->reason,
            'status' => $e->status,
            'registered_by' => $e->registrant?->name,
            'created_at' => $e->created_at->toIso8601String(),
            'exposure_records_count' => $e->exposure_records_count ?? $e->exposureRecords->count(),
            'exposure_records' => $e->relationLoaded('exposureRecords')
                ? $e->exposureRecords->map(fn (ExposureRecord $r) => [
                    'id' => $r->id,
                    'sample_id' => $r->sample_id,
                    'sample_code' => $r->sample?->code,
                    'sample_batch_no' => $r->sample?->batch_no,
                    'sample_status' => $r->sample?->status,
                    'sample_status_label' => $r->sample?->status_label,
                    'overlap_started_at' => $r->overlap_started_at?->toIso8601String(),
                    'overlap_ended_at' => $r->overlap_ended_at?->toIso8601String(),
                    'decision' => $r->decision,
                    'decided_at' => $r->decided_at?->toIso8601String(),
                ])
                : null,
        ];
    }
}

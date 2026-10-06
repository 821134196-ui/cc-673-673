<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sample;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    public function index(Request $request)
    {
        $samples = Sample::query()
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->withCount('observations')
            ->orderByDesc('id')
            ->get()
            ->append(['status_label'])
            ->map($this->formatCard(...));

        return response()->json(['data' => $samples]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:samples,code'],
            'batch_no' => ['required', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $sample = Sample::create($data + ['status' => Sample::STATUS_IN_STOCK]);

        return response()->json(['data' => $this->formatDetail($sample)], 201);
    }

    public function show(Sample $sample)
    {
        $sample->load([
            'storageEvents.incubator',
            'observations' => fn ($q) => $q->with('photos', 'operator')->orderByDesc('created_at'),
            'usages' => fn ($q) => $q->orderByDesc('used_at'),
        ])->append(['status_label']);

        return response()->json(['data' => $this->formatDetail($sample)]);
    }

    private function formatCard(Sample $s): array
    {
        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'batch_no' => $s->batch_no,
            'status' => $s->status,
            'status_label' => $s->status_label,
            'retest_due_at' => $s->retest_due_at?->toIso8601String(),
            'quarantined_at' => $s->quarantined_at?->toIso8601String(),
            'observations_count' => $s->observations_count,
            'retest_overdue' => $s->retestOverdue(),
        ];
    }

    private function formatDetail(Sample $s): array
    {
        return $this->formatCard($s) + [
            'description' => $s->description,
            'usable' => $s->isUsable(),
            'block_reason' => $s->blockReason(),
            'storage_events' => $s->storageEvents->map(fn ($e) => [
                'id' => $e->id,
                'incubator' => ['id' => $e->incubator->id, 'code' => $e->incubator->code, 'name' => $e->incubator->name],
                'started_at' => $e->started_at->toIso8601String(),
                'ended_at' => $e->ended_at?->toIso8601String(),
            ]),
            'observations' => $s->observations->map(fn ($o) => [
                'id' => $o->id,
                'type' => $o->type,
                'type_label' => \App\Models\Observation::TYPES[$o->type] ?? $o->type,
                'notes' => $o->notes,
                'retest_due_at' => $o->retest_due_at?->toIso8601String(),
                'disposal_method' => $o->disposal_method,
                'disposed_by' => $o->disposed_by,
                'operator' => $o->operator?->name,
                'created_at' => $o->created_at->toIso8601String(),
                'photos' => $o->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => url('storage/'.$p->path),
                    'original_name' => $p->original_name,
                ]),
            ]),
            'usages' => $s->usages->map(fn ($u) => [
                'id' => $u->id,
                'experiment_name' => $u->experiment_name,
                'used_at' => $u->used_at->toIso8601String(),
            ]),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Observation;
use App\Models\Sample;
use App\Services\ConclusionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ObservationController extends Controller
{
    public function __construct(private readonly ConclusionService $conclusions)
    {
    }

    /**
     * 为单个样本登记观察 / 复检结论 / 废弃，支持附带照片（本地保存）。
     */
    public function store(Request $request, Sample $sample)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Observation::TYPES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'retest_due_at' => ['nullable', 'date'],
            'disposal_method' => ['nullable', 'string', 'max:128'],
            'disposed_by' => ['nullable', 'string', 'max:64'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['file', 'image', 'max:5120'],
        ]);

        $observation = $this->conclusions->record($sample, $data, $request->user());

        foreach ($request->file('photos', []) as $file) {
            $path = $file->store('observations', 'public');
            $observation->photos()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        $observation->load('photos', 'operator');

        return response()->json([
            'data' => [
                'id' => $observation->id,
                'type' => $observation->type,
                'type_label' => Observation::TYPES[$observation->type] ?? $observation->type,
                'notes' => $observation->notes,
                'disposal_method' => $observation->disposal_method,
                'disposed_by' => $observation->disposed_by,
                'photos' => $observation->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => url('storage/'.$p->path),
                    'original_name' => $p->original_name,
                ]),
            ],
            'sample' => $sample->fresh()->append('status_label'),
        ], 201);
    }
}

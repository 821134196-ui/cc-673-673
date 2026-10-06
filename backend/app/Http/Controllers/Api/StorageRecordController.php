<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StorageRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StorageRecordController extends Controller
{
    /**
     * 登记样本入箱：培养箱位置 + 入箱时间。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sample_id' => ['required', 'exists:samples,id'],
            'incubator_id' => ['required', 'exists:incubators,id'],
            'stored_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // 同一样本存在未出箱记录时，不允许重复入箱
        $open = StorageRecord::where('sample_id', $data['sample_id'])->whereNull('removed_at')->first();
        if ($open) {
            throw ValidationException::withMessages([
                'sample_id' => ['该样本已有未结束的在箱记录，请先登记出箱'],
            ]);
        }

        $record = StorageRecord::create([
            ...$data,
            'stored_at' => Carbon::parse($data['stored_at']),
            'registered_by' => $request->user()->id,
        ]);

        return response()->json($record->load('incubator', 'sample'), 201);
    }

    /**
     * 登记样本出箱时间。
     */
    public function remove(Request $request, StorageRecord $storageRecord): JsonResponse
    {
        if ($storageRecord->removed_at) {
            throw ValidationException::withMessages(['removed_at' => ['该记录已登记出箱']]);
        }

        $data = $request->validate([
            'removed_at' => ['required', 'date', 'after:'.$storageRecord->stored_at->toDateTimeString()],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $storageRecord->update([
            'removed_at' => Carbon::parse($data['removed_at']),
            'note' => $data['note'] ?? $storageRecord->note,
        ]);

        return response()->json($storageRecord->load('incubator', 'sample'));
    }
}

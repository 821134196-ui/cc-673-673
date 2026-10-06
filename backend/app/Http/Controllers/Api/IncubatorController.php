<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incubator;
use App\Models\StorageEvent;
use Illuminate\Http\Request;

class IncubatorController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Incubator::orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:incubators,code'],
            'name' => ['required', 'string', 'max:128'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => Incubator::create($data)], 201);
    }

    /**
     * 登记一条样本存放记录（放入/取出）。
     */
    public function storeStorageEvent(Request $request)
    {
        $data = $request->validate([
            'sample_id' => ['required', 'integer', 'exists:samples,id'],
            'incubator_id' => ['required', 'integer', 'exists:incubators,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
        ]);

        $event = StorageEvent::create($data);

        return response()->json(['data' => $event], 201);
    }
}

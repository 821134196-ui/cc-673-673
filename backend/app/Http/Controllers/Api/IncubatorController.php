<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incubator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncubatorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Incubator::withCount('storageRecords')->orderBy('code')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('incubators', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
        ]);

        $incubator = Incubator::create($data);

        return response()->json($incubator, 201);
    }
}

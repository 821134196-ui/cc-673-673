<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ObservationRecord;
use App\Models\Photo;
use App\Models\Sample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    /**
     * 照片本地保存：支持挂到样本或观察记录下，存入本地 storage/app/public/photos。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:10240'], // 10MB
            'photoable_type' => ['required', 'in:sample,observation'],
            'photoable_id' => ['required', 'integer'],
        ]);

        [$type, $model] = match ($data['photoable_type']) {
            'sample' => ['sample', Sample::findOrFail($data['photoable_id'])],
            'observation' => ['observation', ObservationRecord::findOrFail($data['photoable_id'])],
        };

        $file = $request->file('photo');
        $path = $file->storeAs(
            'photos/'.date('Y/m'),
            time().'_'.\Illuminate\Support\Str::random(8).'.'.$file->getClientOriginalExtension(),
            'public'
        );

        $photo = Photo::create([
            'photoable_type' => $model::class,
            'photoable_id' => $model->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => '/storage/'.$path,
            'original_name' => $photo->original_name,
        ], 201);
    }
}

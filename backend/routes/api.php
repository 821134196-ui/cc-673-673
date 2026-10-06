<?php

use App\Http\Controllers\Api\ActorController;
use App\Http\Controllers\Api\ExposureController;
use App\Http\Controllers\Api\IncubatorController;
use App\Http\Controllers\Api\IsolationController;
use App\Http\Controllers\Api\ObservationController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SampleController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Middleware\EnsureManager;
use App\Http\Middleware\ResolveActor;
use Illuminate\Support\Facades\Route;

Route::middleware(ResolveActor::class)->group(function () {
    Route::get('/', fn () => response()->json([
        'name' => '培养皿污染隔离与观察系统 API',
        'docs' => '见 README.md',
    ]));

    Route::get('/actors', [ActorController::class, 'index']);

    Route::get('/samples', [SampleController::class, 'index']);
    Route::post('/samples', [SampleController::class, 'store']);
    Route::get('/samples/{sample}', [SampleController::class, 'show']);

    Route::get('/incubators', [IncubatorController::class, 'index']);
    Route::post('/incubators', [IncubatorController::class, 'store']);
    Route::post('/storage-events', [IncubatorController::class, 'storeStorageEvent']);

    // 实验员：隔离登记
    Route::get('/isolations', [IsolationController::class, 'index']);
    Route::post('/isolations', [IsolationController::class, 'store']);
    Route::get('/isolations/{isolation}', [IsolationController::class, 'show']);
    Route::post('/isolations/{isolation}/rescan', [IsolationController::class, 'rescan']);

    // 负责人：判定同箱候选是否受影响
    Route::post('/exposures/{exposure}/decide', [ExposureController::class, 'decide'])
        ->middleware(EnsureManager::class);

    // 每个样本独立的观察 / 复检 / 废弃结论
    Route::post('/samples/{sample}/observations', [ObservationController::class, 'store']);

    // 领用（隔离期间拦截）
    Route::post('/samples/{sample}/checkout', [UsageController::class, 'checkout']);

    // 提醒（本地模拟）
    Route::get('/reminders', [ReminderController::class, 'index']);
});

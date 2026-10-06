<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContaminationReportController;
use App\Http\Controllers\Api\IncubatorController;
use App\Http\Controllers\Api\ObservationController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SampleController;
use App\Http\Controllers\Api\StorageRecordController;
use App\Http\Controllers\Api\UsageRequestController;
use Illuminate\Support\Facades\Route;

/*
| 培养皿污染隔离与观察系统 API
| - 实验员(technician)：登记样本、培养箱位置、可疑时间段、观察与废弃
| - 负责人(supervisor)：同箱暴露筛选与逐样本判定
*/

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);

    // 基础资源
    Route::get('/incubators', [IncubatorController::class, 'index']);
    Route::post('/incubators', [IncubatorController::class, 'store']);

    Route::get('/samples', [SampleController::class, 'index']);
    Route::post('/samples', [SampleController::class, 'store']);
    Route::get('/samples/{sample}', [SampleController::class, 'show']);

    // 入箱 / 出箱登记
    Route::post('/storage-records', [StorageRecordController::class, 'store']);
    Route::patch('/storage-records/{storageRecord}/remove', [StorageRecordController::class, 'remove']);

    // 实验员登记疑似污染（样本 + 培养箱 + 可疑时间段）
    Route::post('/contamination-reports', [ContaminationReportController::class, 'store']);
    Route::get('/contamination-reports', [ContaminationReportController::class, 'index']);
    Route::get('/contamination-reports/{contaminationReport}', [ContaminationReportController::class, 'show']);

    // 同箱暴露筛选：任何登录用户可查看结果
    Route::post('/contamination-reports/{contaminationReport}/scan', [ContaminationReportController::class, 'scan']);

    // 逐样本观察/复检/废弃（各样本独立结论）
    Route::post('/samples/{sample}/observations', [ObservationController::class, 'store']);
    Route::post('/samples/{sample}/recheck', [ObservationController::class, 'recheck']);
    Route::post('/samples/{sample}/discard', [ObservationController::class, 'discard']);

    // 领用（隔离期间拦截）
    Route::post('/samples/{sample}/usage-requests', [UsageRequestController::class, 'store']);
    Route::get('/usage-requests', [UsageRequestController::class, 'index']);

    // 照片本地保存
    Route::post('/photos', [PhotoController::class, 'store']);

    // 本地模拟提醒
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::patch('/reminders/{reminder}/read', [ReminderController::class, 'markRead']);

    // 仅负责人：判定样本是否受影响、关闭登记单
    Route::middleware('supervisor')->group(function () {
        Route::post('/exposures/{exposure}/decision', [ContaminationReportController::class, 'decideExposure']);
        Route::patch('/contamination-reports/{contaminationReport}/reviewed', [ContaminationReportController::class, 'markReviewed']);
    });
});

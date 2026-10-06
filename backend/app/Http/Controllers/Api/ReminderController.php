<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    /**
     * 本地模拟提醒接口：按需生成今日复检/逾期提醒，并返回未读列表。
     */
    public function index(Request $request, ReminderService $service): JsonResponse
    {
        $created = $service->generateDueReminders();

        $reminders = Reminder::with('sample')
            ->when(
                $request->boolean('unread_only'),
                fn ($q) => $q->whereNull('read_at')
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'generated_now' => $created->count(),
            'reminders' => $reminders,
        ]);
    }

    public function markRead(Reminder $reminder): JsonResponse
    {
        $reminder->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExposureRecord;
use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExposureController extends Controller
{
    /**
     * 负责人对单条同箱候选做判定：受影响 -> 隔离该样本；未受影响 -> 放行不动。
     * 各样本判定相互独立。
     */
    public function decide(Request $request, ExposureRecord $exposure)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([
                ExposureRecord::DECISION_AFFECTED,
                ExposureRecord::DECISION_NOT_AFFECTED,
            ])],
            'retest_due_at' => ['nullable', 'date', 'after:now'],
        ]);

        $exposure->loadMissing('sample');

        if ($exposure->decision !== ExposureRecord::DECISION_PENDING) {
            abort(422, '该候选已判定，不能重复判定');
        }

        if ($data['decision'] === ExposureRecord::DECISION_AFFECTED
            && $exposure->sample->status !== Sample::STATUS_DISCARDED) {
            $exposure->sample->update([
                'status' => Sample::STATUS_QUARANTINED,
                'quarantined_at' => $exposure->sample->quarantined_at ?? Carbon::now(),
                'retest_due_at' => $data['retest_due_at']
                    ?? Carbon::now()->addDays(config('lab.retest_days', 7)),
            ]);
        }

        $exposure->update([
            'decision' => $data['decision'],
            'decided_by' => $request->user()->id,
            'decided_at' => Carbon::now(),
        ]);

        return response()->json(['data' => $exposure->fresh('sample')]);
    }
}

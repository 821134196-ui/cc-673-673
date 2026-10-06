<?php

namespace Tests\Feature;

use App\Models\ContaminationReport;
use App\Models\Incubator;
use App\Models\ObservationRecord;
use App\Models\Sample;
use App\Models\StorageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 核心业务规则验证：
 *  A. 同箱时间范围筛选——后来才放入 / 窗口前已取出不算暴露
 *  B. 隔离期间领用拦截——复检未通过继续冻结
 *  C. 不同样本独立结论；废弃保留批次与历史
 */
class ContainmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $tech;
    private User $lead;
    private Incubator $incubator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tech = User::factory()->create(['role' => User::ROLE_TECHNICIAN]);
        $this->lead = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->incubator = Incubator::create(['code' => 'INC-A', 'name' => 'A箱']);
        $this->actingAs($this->tech);
    }

    private function sample(string $code): Sample
    {
        return Sample::create([
            'code' => $code, 'name' => $code, 'batch_no' => 'B-1',
            'status' => Sample::STATUS_NORMAL,
        ]);
    }

    private function store(Sample $s, string $in, ?string $out = null): StorageRecord
    {
        return StorageRecord::create([
            'sample_id' => $s->id,
            'incubator_id' => $this->incubator->id,
            'stored_at' => $in,
            'removed_at' => $out,
        ]);
    }

    private function report(string $start, string $end, int $suspectId): array
    {
        return $this->postJson('/api/contamination-reports', [
            'incubator_id' => $this->incubator->id,
            'suspect_sample_id' => $suspectId,
            'suspected_start_at' => $start,
            'suspected_end_at' => $end,
        ])->assertCreated()->json();
    }

    // ---------------------------------------------------------------- A. 同箱筛选

    public function test_overlap_scan_only_returns_samples_actually_present_during_window(): void
    {
        $suspect = $this->sample('P-SUS');           // 09-30 入箱，仍在
        $coInside = $this->sample('P-IN');           // 窗口内同箱
        $leftBefore = $this->sample('P-BEFORE');     // 窗口开始前已取出
        $putAfter = $this->sample('P-AFTER');        // 窗口结束后才放入
        $anotherBox = $this->sample('P-OTHER');      // 从未来过此箱

        $this->store($suspect, '2026-09-30 08:00');
        $this->store($coInside, '2026-10-02 09:00', '2026-10-04 18:00');
        $this->store($leftBefore, '2026-09-25 09:00', '2026-09-29 18:00');
        $this->store($putAfter, '2026-10-06 10:00');
        $otherInc = Incubator::create(['code' => 'INC-B', 'name' => 'B箱']);
        StorageRecord::create([
            'sample_id' => $anotherBox->id, 'incubator_id' => $otherInc->id,
            'stored_at' => '2026-10-02 09:00', 'removed_at' => null,
        ]);

        // 可疑窗口 10-02 00:00 ~ 10-05 00:00
        $r = $this->report('2026-10-02 00:00:00', '2026-10-05 00:00:00', $suspect->id);

        $scan = $this->postJson("/api/contamination-reports/{$r['id']}/scan")
            ->assertOk()->json();

        $present = collect($scan['present'])->pluck('sample.code')->all();
        sort($present);
        $this->assertSame(['P-IN'], $present, '仅窗口内实际同箱的 P-IN 应被命中');
        $this->assertSame(3, $scan['checked']);

        // 明细中每个候选都带证据：命中者有交集区间，未命中者无
        $detail = $this->getJson("/api/contamination-reports/{$r['id']}")->json();
        $byCode = collect($detail['exposures'])->keyBy(fn ($e) => $e['sample']['code']);

        $this->assertTrue($byCode['P-IN']['was_present']);
        $this->assertSame('2026-10-02 09:00:00', $byCode['P-IN']['overlap_start']);
        $this->assertSame('2026-10-04 18:00:00', $byCode['P-IN']['overlap_end']);
        $this->assertFalse($byCode['P-BEFORE']['was_present'], '窗口开始前已取出不算暴露');
        $this->assertFalse($byCode['P-AFTER']['was_present'], '后来才放入不算暴露');
        $this->assertArrayNotHasKey('P-OTHER', $byCode->all(), '从未在此箱存放过的样本不参与该箱筛选');
    }

    public function test_boundary_samples_are_not_exposed(): void
    {
        // 边界严格性：出箱时间 == 窗口开始（半开区间，不算）；入箱时间 == 窗口结束（不算）
        $suspect = $this->sample('S');
        $this->store($suspect, '2026-09-01 08:00');
        $edgeOut = $this->sample('EDGE-OUT');   // 10-02 00:00 整出箱
        $edgeIn = $this->sample('EDGE-IN');     // 10-05 00:00 整入箱
        $this->store($edgeOut, '2026-10-01 08:00', '2026-10-02 00:00:00');
        $this->store($edgeIn, '2026-10-05 00:00:00');

        $r = $this->report('2026-10-02 00:00:00', '2026-10-05 00:00:00', $suspect->id);
        $scan = $this->actingAs($this->tech)
            ->postJson("/api/contamination-reports/{$r['id']}/scan")->assertOk()->json();
        $this->assertSame(0, $scan['present_count'], '恰好在边界点的样本不算同箱暴露');
    }

    public function test_sample_still_in_incubator_during_window_is_exposed(): void
    {
        // 窗口开始前入箱、窗口结束时仍未取出（仍在箱）→ 必须命中
        $suspect = $this->sample('S');
        $this->store($suspect, '2026-09-01 08:00');
        $staying = $this->sample('STAY');
        $this->store($staying, '2026-09-28 08:00');

        $r = $this->report('2026-10-02 00:00:00', '2026-10-05 00:00:00', $suspect->id);
        $scan = $this->actingAs($this->tech)
            ->postJson("/api/contamination-reports/{$r['id']}/scan")->assertOk()->json();
        $this->assertSame(['STAY'], collect($scan['present'])->pluck('sample.code')->all());
    }

    // ---------------------------------------------------------------- B. 领用拦截

    public function test_usage_is_blocked_during_quarantine_and_allowed_after_release(): void
    {
        $s = $this->sample('P-1');
        $r = $this->report('2026-10-02 00:00:00', '2026-10-05 00:00:00', $s->id);

        $s->refresh();
        $this->assertSame(Sample::STATUS_QUARANTINED, $s->status);

        // 隔离期间领用 → 423 拦截并留痕
        $this->actingAs($this->tech)
            ->postJson("/api/samples/{$s->id}/usage-requests", ['experiment_name' => '后续扩增'])
            ->assertStatus(423)
            ->assertJsonPath('status', 'blocked')
            ->assertJsonPath('block_reason', '样本处于隔离观察期，不得领用');

        // 复检未通过 → 继续冻结，仍拦截
        $this->postJson("/api/samples/{$s->id}/recheck", [
            'result' => 'failed', 'content' => '仍有杂菌', 'next_recheck_deadline' => '2026-10-20',
        ])->assertOk();
        $s->refresh();
        $this->assertSame(Sample::STATUS_FROZEN, $s->status);
        $this->assertSame('2026-10-20', $s->recheck_deadline->toDateString());
        $this->postJson("/api/samples/{$s->id}/usage-requests", ['experiment_name' => 'X'])
            ->assertStatus(423)
            ->assertJsonPath('block_reason', '样本复检未通过，处于继续冻结状态，不得领用');

        // 复检通过 → 解除隔离，领用放行
        $this->postJson("/api/samples/{$s->id}/recheck", ['result' => 'passed', 'content' => '无菌'])
            ->assertOk();
        $s->refresh();
        $this->assertSame(Sample::STATUS_RELEASED, $s->status);
        $this->postJson("/api/samples/{$s->id}/usage-requests", ['experiment_name' => '后续扩增'])
            ->assertCreated()
            ->assertJsonPath('status', 'approved');
    }

    public function test_discarded_sample_cannot_be_used(): void
    {
        $s = $this->sample('P-D');
        $this->actingAs($this->tech)
            ->postJson("/api/samples/{$s->id}/discard", [
                'disposal_method' => '高压灭菌',
                'executed_by_name' => '小李',
            ])->assertOk();

        $this->postJson("/api/samples/{$s->id}/usage-requests", ['experiment_name' => 'X'])
            ->assertStatus(423)
            ->assertJsonPath('status', 'blocked');
    }

    // ---------------------------------------------------------------- C. 独立结论 & 废弃保留

    public function test_supervisor_decides_each_sample_independently(): void
    {
        $suspect = $this->sample('S');
        $a = $this->sample('A');  // 同箱暴露
        $b = $this->sample('B');  // 同箱暴露，但另一样本
        $this->store($suspect, '2026-10-01 08:00');
        $this->store($a, '2026-10-02 08:00', '2026-10-04 08:00');
        $this->store($b, '2026-10-02 08:00', '2026-10-04 08:00');

        $r = $this->report('2026-10-02 00:00:00', '2026-10-05 00:00:00', $suspect->id);
        $this->actingAs($this->tech)->postJson("/api/contamination-reports/{$r['id']}/scan")->assertOk();

        $exps = $this->getJson("/api/contamination-reports/{$r['id']}")->json()['exposures'];
        $expA = collect($exps)->firstWhere('sample.code', 'A');
        $expB = collect($exps)->firstWhere('sample.code', 'B');

        // 实验员无权判定
        $this->actingAs($this->tech)
            ->postJson("/api/exposures/{$expA['id']}/decision", ['decision' => 'exposed'])
            ->assertForbidden();

        // 负责人：A 判定受影响并隔离；B 判定不受影响——结论互相独立
        $this->actingAs($this->lead)
            ->postJson("/api/exposures/{$expA['id']}/decision", [
                'decision' => 'exposed', 'recheck_deadline' => '2026-10-12',
            ])->assertOk();
        $this->actingAs($this->lead)
            ->postJson("/api/exposures/{$expB['id']}/decision", ['decision' => 'cleared'])
            ->assertOk();

        $a->refresh();
        $b->refresh();
        $this->assertSame(Sample::STATUS_QUARANTINED, $a->status);
        $this->assertSame('2026-10-12', $a->recheck_deadline->toDateString());
        $this->assertSame(Sample::STATUS_NORMAL, $b->status, 'B 的独立结论为不受影响，保持正常');
        $this->assertNull($b->recheck_deadline);

        // A 复检失败冻结不影响 B；B 仍可领用
        $this->actingAs($this->tech)
            ->postJson("/api/samples/{$a->id}/recheck", ['result' => 'failed'])->assertOk();
        $this->postJson("/api/samples/{$b->id}/usage-requests", ['experiment_name' => '后续实验'])
            ->assertCreated()->assertJsonPath('status', 'approved');

        // A 通过复检解除，不影响 B 的历史
        $this->postJson("/api/samples/{$a->id}/recheck", ['result' => 'passed'])->assertOk();
        $this->assertSame(Sample::STATUS_RELEASED, $a->fresh()->status);
        $this->assertSame(Sample::STATUS_NORMAL, $b->fresh()->status);
    }

    public function test_discard_keeps_batch_and_full_observation_history(): void
    {
        $s = $this->sample('P-KEEP');
        $this->actingAs($this->tech)->postJson("/api/samples/{$s->id}/observations", ['content' => '观察1：边缘菌落'])->assertCreated();
        $this->postJson("/api/samples/{$s->id}/observations", ['content' => '观察2：菌落扩大'])->assertCreated();

        $this->postJson("/api/samples/{$s->id}/discard", [
            'disposal_method' => '高压蒸汽灭菌 121℃ 30min',
            'executed_by_name' => '实验员小李',
            'content' => '确认污染，废弃',
        ])->assertOk();

        $detail = $this->getJson("/api/samples/{$s->id}")->json();
        $this->assertSame('discarded', $detail['status']);
        $this->assertSame('B-1', $detail['batch_no'], '原批次号必须保留');
        $this->assertCount(3, $detail['observations'], '两条观察 + 一条废弃记录全部保留');
        $disposal = collect($detail['observations'])->firstWhere('type', 'disposal');
        $this->assertSame('高压蒸汽灭菌 121℃ 30min', $disposal['disposal_method']);
        $this->assertSame('实验员小李', $disposal['executed_by_name']);
        $this->assertNotNull($disposal['executed_at']);

        // 样本实体仍然存在（未被删除）
        $this->assertDatabaseHas('samples', ['id' => $s->id, 'status' => 'discarded', 'batch_no' => 'B-1']);
    }

    public function test_recheck_records_are_written_per_sample(): void
    {
        $a = $this->sample('A');
        $b = $this->sample('B');
        $this->actingAs($this->tech);
        $this->postJson("/api/samples/{$a->id}/recheck", ['result' => 'passed', 'content' => 'A 合格'])->assertOk();
        $this->postJson("/api/samples/{$b->id}/recheck", ['result' => 'failed', 'content' => 'B 不合格'])->assertOk();

        $this->assertDatabaseHas('observation_records', [
            'sample_id' => $a->id, 'type' => 'recheck', 'result' => 'passed',
        ]);
        $this->assertDatabaseHas('observation_records', [
            'sample_id' => $b->id, 'type' => 'recheck', 'result' => 'failed',
        ]);
        $this->assertSame(Sample::STATUS_RELEASED, $a->fresh()->status);
        $this->assertSame(Sample::STATUS_FROZEN, $b->fresh()->status);
    }

    public function test_discarding_requires_method_and_executor(): void
    {
        $s = $this->sample('P-X');
        $this->actingAs($this->tech)
            ->postJson("/api/samples/{$s->id}/discard", ['disposal_method' => ''])
            ->assertStatus(422);
        $this->assertSame(Sample::STATUS_NORMAL, $s->fresh()->status);
    }

    public function test_report_validates_time_window_order(): void
    {
        $s = $this->sample('P-X');
        $this->actingAs($this->tech)
            ->postJson('/api/contamination-reports', [
                'incubator_id' => $this->incubator->id,
                'suspect_sample_id' => $s->id,
                'suspected_start_at' => '2026-10-05 00:00:00',
                'suspected_end_at' => '2026-10-02 00:00:00',
            ])->assertStatus(422);
        $this->assertSame(Sample::STATUS_NORMAL, $s->fresh()->status, '非法时间窗不应触发隔离');
    }

    public function test_reminder_generation_for_due_and_overdue_samples(): void
    {
        $due = $this->sample('DUE');
        $due->update(['status' => Sample::STATUS_QUARANTINED, 'recheck_deadline' => '2026-10-06']);
        $overdue = $this->sample('LATE');
        $overdue->update(['status' => Sample::STATUS_FROZEN, 'recheck_deadline' => '2026-10-01']);
        $normal = $this->sample('OK');
        $normal->update(['recheck_deadline' => '2026-10-06']);

        $svc = app(\App\Services\ReminderService::class);
        $created = $svc->generateDueReminders(\Carbon\Carbon::parse('2026-10-06'));
        $this->assertCount(2, $created, '当天到期 + 逾期各一条；正常样本不提醒');

        // 幂等：同一天重复运行不产生新提醒
        $again = $svc->generateDueReminders(\Carbon\Carbon::parse('2026-10-06'));
        $this->assertCount(0, $again);
    }
}

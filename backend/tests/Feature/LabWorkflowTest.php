<?php

namespace Tests\Feature;

use App\Models\ExposureRecord;
use App\Models\Incubator;
use App\Models\IsolationEvent;
use App\Models\Observation;
use App\Models\Sample;
use App\Models\StorageEvent;
use App\Models\User;
use App\Services\ExposureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LabWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $tech;
    private User $manager;
    private Incubator $inc;
    private Incubator $otherInc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tech = User::factory()->create(['role' => User::ROLE_TECHNICIAN]);
        $this->manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $this->inc = Incubator::create(['code' => 'INC-A1', 'name' => 'A1']);
        $this->otherInc = Incubator::create(['code' => 'INC-B2', 'name' => 'B2']);
    }

    private function sample(string $code): Sample
    {
        return Sample::create(['code' => $code, 'batch_no' => 'B1', 'name' => $code]);
    }

    private function store(Sample $s, Incubator $inc, string $start, ?string $end): void
    {
        StorageEvent::create([
            'sample_id' => $s->id,
            'incubator_id' => $inc->id,
            'started_at' => Carbon::parse($start),
            'ended_at' => $end ? Carbon::parse($end) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function registerIsolation(Sample $source): array
    {
        return $this->postJson('/api/isolations', [
            'source_sample_id' => $source->id,
            'incubator_id' => $this->inc->id,
            'suspicious_started_at' => '2026-10-04 00:00:00',
            'suspicious_ended_at' => '2026-10-05 00:00:00',
            'reason' => '异常菌落',
        ], ['X-Actor-Id' => $this->tech->id])->json('data');
    }

    /**
     * 验证一：同箱时间范围筛选。
     * 只有与可疑时间段严格重叠的样本才被筛出；后来放入、之前取出、其他培养箱均排除。
     */
    public function test_co_storage_exposure_window_filtering(): void
    {
        $source = $this->sample('P-SRC');
        $inside = $this->sample('P-INSIDE');   // 窗口内同箱
        $later = $this->sample('P-LATER');     // 窗口结束后才放入
        $before = $this->sample('P-BEFORE');   // 窗口开始前已取出
        $elsewhere = $this->sample('P-ELSE');  // 其他培养箱
        $touching = $this->sample('P-TOUCH');  // 恰在窗口结束时刻放入（相接不算重叠）

        $this->store($source, $this->inc, '2026-10-03 08:00', null);
        $this->store($inside, $this->inc, '2026-10-04 12:00', '2026-10-04 20:00');
        $this->store($later, $this->inc, '2026-10-05 09:00', null);
        $this->store($before, $this->inc, '2026-10-02 08:00', '2026-10-03 20:00');
        $this->store($elsewhere, $this->otherInc, '2026-10-04 08:00', null);
        $this->store($touching, $this->inc, '2026-10-05 00:00', null);

        $eventData = $this->registerIsolation($source);

        $exposedIds = ExposureRecord::where('isolation_event_id', $eventData['id'])
            ->pluck('sample_id')->all();

        $this->assertContains($inside->id, $exposedIds, '窗口内同箱样本必须被筛出');
        $this->assertNotContains($later->id, $exposedIds, '可疑时间段后才放入的样本不算同箱暴露');
        $this->assertNotContains($before->id, $exposedIds, '可疑时间段开始前已取出的样本不算暴露');
        $this->assertNotContains($elsewhere->id, $exposedIds, '其他培养箱的样本不算暴露');
        $this->assertNotContains($touching->id, $exposedIds, '仅时间点相接不算共同存放');
        $this->assertNotContains($source->id, $exposedIds, '来源样本自身不作为候选');
        $this->assertCount(1, $exposedIds);

        // 交集快照应等于实际共同存放区间
        $record = ExposureRecord::where('sample_id', $inside->id)->first();
        $this->assertEquals('2026-10-04 12:00:00', $record->overlap_started_at->toDateTimeString());
        $this->assertEquals('2026-10-04 20:00:00', $record->overlap_ended_at->toDateTimeString());

        // 服务层直测：仍在箱中的样本（ended_at NULL）与开放窗口
        Carbon::setTestNow('2026-10-06 12:00:00');
        $open = IsolationEvent::create([
            'source_sample_id' => $source->id,
            'incubator_id' => $this->inc->id,
            'suspicious_started_at' => '2026-10-05 20:00:00',
            'suspicious_ended_at' => '2026-10-06 00:00:00',
            'registered_by' => $this->tech->id,
        ]);
        $scanned = app(ExposureService::class)->scan($open);
        $this->assertContains($later->id, $scanned->pluck('sample.id')->all(),
            '窗口前放入且至今在箱的样本应重叠');
        Carbon::setTestNow();
    }

    /**
     * 验证二：隔离期间领用拦截；复检未通过继续拦截；放行后可领用。
     */
    public function test_checkout_blocked_during_quarantine_and_freeze(): void
    {
        $source = $this->sample('P-SRC');
        $normal = $this->sample('P-OK');

        // 正常样本可领用
        $this->postJson('/api/samples/'.$normal->id.'/checkout',
            ['experiment_name' => '实验 A'], ['X-Actor-Id' => $this->tech->id])
            ->assertCreated();

        $this->registerIsolation($source);

        // 隔离登记后来源样本立即进入隔离
        $this->assertSame(Sample::STATUS_QUARANTINED, $source->fresh()->status);
        $this->assertNotNull($source->fresh()->retest_due_at);

        // 隔离中领用 -> 409
        $this->postJson('/api/samples/'.$source->id.'/checkout',
            ['experiment_name' => '实验 B'], ['X-Actor-Id' => $this->tech->id])
            ->assertStatus(409)
            ->assertJsonPath('sample.status', Sample::STATUS_QUARANTINED);
        $this->assertDatabaseCount('sample_usages', 1); // 没有新增领用记录

        // 复检未通过 -> 冻结，仍拦截
        $this->postJson('/api/samples/'.$source->id.'/observations', [
            'type' => Observation::TYPE_RETEST_FAIL,
            'notes' => '仍有杂菌',
            'retest_due_at' => '2026-10-12 00:00:00',
        ], ['X-Actor-Id' => $this->tech->id])->assertCreated();

        $this->assertSame(Sample::STATUS_FROZEN, $source->fresh()->status);
        $this->postJson('/api/samples/'.$source->id.'/checkout',
            ['experiment_name' => '实验 C'], ['X-Actor-Id' => $this->tech->id])
            ->assertStatus(409)
            ->assertJsonPath('sample.status', Sample::STATUS_FROZEN);

        // 复检未通过必须给出下次复检截止日
        $this->postJson('/api/samples/'.$source->id.'/observations', [
            'type' => Observation::TYPE_RETEST_FAIL,
        ], ['X-Actor-Id' => $this->tech->id])->assertStatus(422);

        // 复检通过 -> 放行 -> 可领用
        $this->postJson('/api/samples/'.$source->id.'/observations', [
            'type' => Observation::TYPE_RETEST_PASS,
        ], ['X-Actor-Id' => $this->tech->id])->assertCreated();

        $this->assertSame(Sample::STATUS_RELEASED, $source->fresh()->status);
        $this->assertNull($source->fresh()->retest_due_at);
        $this->postJson('/api/samples/'.$source->id.'/checkout',
            ['experiment_name' => '实验 D'], ['X-Actor-Id' => $this->tech->id])
            ->assertCreated();
    }

    /**
     * 验证三：不同样本的结论相互独立；废弃保留批次与历史。
     */
    public function test_independent_conclusions_per_sample(): void
    {
        $a = $this->sample('P-A');
        $b = $this->sample('P-B');
        $this->store($a, $this->inc, '2026-10-04 08:00', null);
        $this->store($b, $this->inc, '2026-10-04 09:00', null);

        $eventData = $this->registerIsolation($a);
        $candidate = ExposureRecord::where('isolation_event_id', $eventData['id'])
            ->where('sample_id', $b->id)->firstOrFail();

        // 负责人确认 B 受影响 -> B 隔离（不影响 A 的任何后续判定）
        $this->postJson('/api/exposures/'.$candidate->id.'/decide', [
            'decision' => ExposureRecord::DECISION_AFFECTED,
        ], ['X-Actor-Id' => $this->manager->id])->assertSuccessful();
        $this->assertSame(Sample::STATUS_QUARANTINED, $b->fresh()->status);

        // 实验员不能做负责人判定
        $other = $this->sample('P-C');
        $this->store($other, $this->inc, '2026-10-04 10:00', null);
        $this->postJson('/api/isolations', [
            'source_sample_id' => $a->id,
            'incubator_id' => $this->inc->id,
            'suspicious_started_at' => '2026-10-04 00:00:00',
            'suspicious_ended_at' => '2026-10-05 00:00:00',
        ], ['X-Actor-Id' => $this->tech->id])->assertCreated();
        $cRecord = ExposureRecord::where('sample_id', $other->id)->firstOrFail();
        $this->postJson('/api/exposures/'.$cRecord->id.'/decide', [
            'decision' => ExposureRecord::DECISION_NOT_AFFECTED,
        ], ['X-Actor-Id' => $this->tech->id])->assertStatus(403);

        // A 复检通过；B 独立废弃：互不影响
        $this->postJson('/api/samples/'.$a->id.'/observations', [
            'type' => Observation::TYPE_RETEST_PASS,
            'notes' => 'A 阴性',
        ], ['X-Actor-Id' => $this->tech->id])->assertCreated();

        // B 隔离期间先有一条观察记录，再废弃
        $this->postJson('/api/samples/'.$b->id.'/observations', [
            'type' => Observation::TYPE_OBSERVATION,
            'notes' => 'B 培养皿边缘出现菌丝',
        ], ['X-Actor-Id' => $this->tech->id])->assertCreated();

        $this->postJson('/api/samples/'.$b->id.'/observations', [
            'type' => Observation::TYPE_DISCARD,
            'disposal_method' => '高压灭菌 121℃ 20min',
            'disposed_by' => '李实验',
            'notes' => 'B 污染无法恢复',
        ], ['X-Actor-Id' => $this->tech->id])->assertCreated();

        $this->assertSame(Sample::STATUS_RELEASED, $a->fresh()->status, 'A 的结论不受 B 废弃影响');
        $this->assertSame(Sample::STATUS_DISCARDED, $b->fresh()->status);

        // 废弃必须有处理方式与执行人
        $d = $this->sample('P-D');
        $this->postJson('/api/samples/'.$d->id.'/observations', [
            'type' => Observation::TYPE_DISCARD,
        ], ['X-Actor-Id' => $this->tech->id])->assertStatus(422);

        // 废弃后：批次保留、历史保留、不能再登记结论/领用
        $this->assertSame('B1', $b->fresh()->batch_no, '原批次号必须保留');
        $this->assertGreaterThanOrEqual(2, $b->observations()->count(), '观察历史必须保留（隔离候选期间+废弃）');
        $this->postJson('/api/samples/'.$b->id.'/observations', [
            'type' => Observation::TYPE_OBSERVATION,
        ], ['X-Actor-Id' => $this->tech->id])->assertStatus(422);
        $this->postJson('/api/samples/'.$b->id.'/checkout',
            ['experiment_name' => 'X'], ['X-Actor-Id' => $this->tech->id])
            ->assertStatus(409);
    }

    public function test_reminder_endpoint_and_command(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $overdue = $this->sample('P-OVERDUE');
        $overdue->update([
            'status' => Sample::STATUS_QUARANTINED,
            'retest_due_at' => Carbon::parse('2026-10-05 00:00:00'),
        ]);

        $this->getJson('/api/reminders', ['X-Actor-Id' => $this->tech->id])
            ->assertOk()
            ->assertJsonPath('data.0.code', 'P-OVERDUE')
            ->assertJsonPath('data.0.overdue', true);

        $this->artisan('lab:retest-remind')->assertSuccessful();
        Carbon::setTestNow();
    }
}

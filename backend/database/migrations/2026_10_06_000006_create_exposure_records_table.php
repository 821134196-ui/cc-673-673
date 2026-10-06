<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 同箱暴露记录：系统按时间重叠筛出的候选样本，由负责人逐条判定
        Schema::create('exposure_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolation_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->timestamp('overlap_started_at')->nullable(); // 共同存放交集起点（快照）
            $table->timestamp('overlap_ended_at')->nullable();   // 共同存放交集终点（快照）
            // pending 待判定 | affected 确认受影响 | not_affected 确认未受影响
            $table->string('decision')->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['isolation_event_id', 'sample_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exposure_records');
    }
};

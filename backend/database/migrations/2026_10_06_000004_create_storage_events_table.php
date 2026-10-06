<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 存放事件：样本在某培养箱内存放的时间区间。
        // ended_at 为空表示当前仍在该培养箱中。
        // 同箱暴露筛选以此为唯一依据。
        Schema::create('storage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incubator_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['incubator_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_events');
    }
};

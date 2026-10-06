<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 隔离登记：实验员发现疑似污染时登记来源样本、培养箱与可疑时间段
        Schema::create('isolation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_sample_id')->constrained('samples');
            $table->foreignId('incubator_id')->constrained('incubators');
            $table->timestamp('suspicious_started_at');
            $table->timestamp('suspicious_ended_at');
            $table->text('reason')->nullable();
            // open 待负责人判定 | closed 判定完成
            $table->string('status')->default('open')->index();
            $table->foreignId('registered_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isolation_events');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exposures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contamination_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            // 筛选结果：可疑时间段内是否确实在同一培养箱存放过
            $table->boolean('was_present')->default(false)->index();
            $table->dateTime('overlap_start')->nullable()->comment('共同存放交集起（证据）');
            $table->dateTime('overlap_end')->nullable()->comment('共同存放交集止（证据）');
            $table->json('evidence')->nullable()->comment('命中的存放区间明细');
            // pending=待判定 / exposed=确认受影响，隔离 / cleared=确认不受影响
            $table->string('decision')->default('pending')->index();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->date('recheck_deadline')->nullable()->comment('该样本本次隔离的复检截止日');
            $table->timestamps();

            $table->unique(['contamination_report_id', 'sample_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exposures');
    }
};

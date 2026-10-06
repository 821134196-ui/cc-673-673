<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // 培养皿/样本编号
            $table->string('batch_no')->index();       // 原批次号，废弃后仍保留
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            // in_stock 正常 | quarantined 隔离中 | released 复检通过放行
            // frozen 复检未通过继续冻结 | discarded 已废弃
            $table->string('status')->default('in_stock')->index();
            $table->timestamp('retest_due_at')->nullable(); // 复检截止日
            $table->timestamp('quarantined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};

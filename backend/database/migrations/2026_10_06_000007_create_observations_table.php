<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 每个样本各自独立的观察 / 复检 / 废弃结论历史
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            // observation 观察记录 | retest_pass 复检通过 | retest_fail 复检未通过 | discard 废弃
            $table->string('type');
            $table->text('notes')->nullable();
            $table->timestamp('retest_due_at')->nullable();  // 本次结论给出的下次复检截止日
            $table->string('disposal_method')->nullable();  // 废弃处理方式，如 高压灭菌
            $table->string('disposed_by')->nullable();      // 废弃执行人
            $table->foreignId('operator_id')->constrained('users');
            $table->timestamps();

            $table->index(['sample_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};

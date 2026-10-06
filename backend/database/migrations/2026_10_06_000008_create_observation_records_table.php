<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            // observation=日常观察 / recheck=复检结论 / disposal=废弃登记 / release=解除隔离
            $table->string('type')->index();
            $table->text('content')->nullable()->comment('观察内容/备注');
            // recheck: passed=复检通过 / failed=复检未通过（继续冻结）
            $table->string('result')->nullable();
            $table->string('disposal_method')->nullable()->comment('废弃处理方式，如高压灭菌、焚烧');
            $table->string('executed_by_name')->nullable()->comment('废弃执行人姓名');
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('executed_at')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete()->comment('记录人');
            $table->timestamps();

            $table->index(['sample_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_records');
    }
};

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
            $table->string('code')->unique()->comment('培养皿/样本编号');
            $table->string('name')->nullable()->comment('样本名称');
            $table->string('batch_no')->index()->comment('原始批次号（废弃后仍保留）');
            $table->text('description')->nullable();
            // normal=正常 / quarantined=隔离观察中 / frozen=复检未通过继续冻结 / released=已解除隔离 / discarded=已废弃
            $table->string('status')->default('normal')->index();
            $table->date('recheck_deadline')->nullable()->comment('复检截止日');
            $table->dateTime('quarantined_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};

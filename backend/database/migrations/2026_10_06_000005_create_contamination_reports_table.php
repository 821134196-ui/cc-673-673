<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contamination_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no')->unique()->comment('污染疑似登记编号');
            $table->foreignId('incubator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('suspect_sample_id')->nullable()->constrained('samples')->comment('首先发现疑似污染的培养皿，登记即隔离');
            $table->dateTime('suspected_start_at')->comment('可疑时间段起');
            $table->dateTime('suspected_end_at')->comment('可疑时间段止');
            $table->text('description')->nullable();
            // open=待负责人判定 / reviewed=已判定 / closed=已关闭
            $table->string('status')->default('open')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contamination_reports');
    }
};

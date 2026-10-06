<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->string('experiment_name')->comment('拟用于的后续实验');
            // approved=允许领用 / blocked=被拦截
            $table->string('status')->index();
            $table->string('block_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('photoable_type');
            $table->unsignedBigInteger('photoable_id');
            $table->string('path')->comment('本地存储相对路径');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['photoable_type', 'photoable_id']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            // recheck_due_today=复检截止日当天 / recheck_overdue=复检已逾期
            $table->string('type')->index();
            $table->string('channel')->default('local')->comment('本地模拟，不外发');
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('remind_date');
            $table->dateTime('sent_at')->nullable()->comment('模拟发送时间');
            $table->dateTime('read_at')->nullable();
            $table->string('dedupe_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('usage_requests');
    }
};

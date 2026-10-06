<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quarantines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contamination_report_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('started_at')->comment('隔离开始时间');
            $table->dateTime('ended_at')->nullable()->comment('解除/废弃时间，NULL 表示仍在隔离');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('release_note')->nullable();
            $table->timestamps();

            $table->index(['sample_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarantines');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incubator_id')->constrained()->cascadeOnDelete();
            $table->dateTime('stored_at')->comment('入箱时间')->index();
            $table->dateTime('removed_at')->nullable()->comment('出箱时间，NULL 表示仍在箱内')->index();
            $table->string('note')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // 同箱重叠筛选的核心索引：按培养箱 + 入箱时间
            $table->index(['incubator_id', 'stored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_records');
    }
};

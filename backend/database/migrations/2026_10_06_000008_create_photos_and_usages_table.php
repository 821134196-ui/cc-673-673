<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('observation_id')->constrained()->cascadeOnDelete();
            $table->string('path');              // storage/app/public 下的相对路径
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        // 成功领用（用于后续实验）记录；隔离/冻结/废弃样本的领用请求会被拦截
        Schema::create('sample_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->string('experiment_name');
            $table->foreignId('used_by')->constrained('users');
            $table->timestamp('used_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_usages');
        Schema::dropIfExists('photos');
    }
};

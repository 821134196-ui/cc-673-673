<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ObservationRecord extends Model
{
    use HasFactory;

    public const TYPE_OBSERVATION = 'observation';
    public const TYPE_RECHECK = 'recheck';
    public const TYPE_DISPOSAL = 'disposal';
    public const TYPE_RELEASE = 'release';

    public const RESULT_PASSED = 'passed';
    public const RESULT_FAILED = 'failed';

    protected $fillable = [
        'sample_id', 'type', 'content', 'result',
        'disposal_method', 'executed_by_name', 'executed_by_user_id',
        'executed_at', 'operator_id',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'photoable');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_OBSERVATION => '观察',
            self::TYPE_RECHECK => '复检',
            self::TYPE_DISPOSAL => '废弃',
            self::TYPE_RELEASE => '解除隔离',
            default => $this->type,
        };
    }
}

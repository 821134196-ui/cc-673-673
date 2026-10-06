<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Observation extends Model
{
    use HasFactory;

    public const TYPE_OBSERVATION = 'observation'; // 观察
    public const TYPE_RETEST_PASS = 'retest_pass'; // 复检通过
    public const TYPE_RETEST_FAIL = 'retest_fail'; // 复检未通过
    public const TYPE_DISCARD = 'discard';         // 废弃

    public const TYPES = [
        self::TYPE_OBSERVATION => '观察',
        self::TYPE_RETEST_PASS => '复检通过',
        self::TYPE_RETEST_FAIL => '复检未通过',
        self::TYPE_DISCARD => '废弃',
    ];

    protected $fillable = [
        'sample_id', 'type', 'notes', 'retest_due_at',
        'disposal_method', 'disposed_by', 'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'retest_due_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return HasMany<Photo> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}

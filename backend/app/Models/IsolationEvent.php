<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IsolationEvent extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'source_sample_id', 'incubator_id',
        'suspicious_started_at', 'suspicious_ended_at',
        'reason', 'status', 'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'suspicious_started_at' => 'datetime',
            'suspicious_ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sample, $this> */
    public function sourceSample(): BelongsTo
    {
        return $this->belongsTo(Sample::class, 'source_sample_id');
    }

    /** @return BelongsTo<Incubator, $this> */
    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** @return HasMany<ExposureRecord> */
    public function exposureRecords(): HasMany
    {
        return $this->hasMany(ExposureRecord::class);
    }
}

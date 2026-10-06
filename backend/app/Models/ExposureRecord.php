<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExposureRecord extends Model
{
    use HasFactory;

    public const DECISION_PENDING = 'pending';
    public const DECISION_AFFECTED = 'affected';
    public const DECISION_NOT_AFFECTED = 'not_affected';

    protected $fillable = [
        'isolation_event_id', 'sample_id',
        'overlap_started_at', 'overlap_ended_at',
        'decision', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'overlap_started_at' => 'datetime',
            'overlap_ended_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IsolationEvent, $this> */
    public function isolationEvent(): BelongsTo
    {
        return $this->belongsTo(IsolationEvent::class);
    }

    /** @return BelongsTo<Sample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }
}

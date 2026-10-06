<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exposure extends Model
{
    use HasFactory;

    public const DECISION_PENDING = 'pending';
    public const DECISION_EXPOSED = 'exposed';
    public const DECISION_CLEARED = 'cleared';

    protected $fillable = [
        'contamination_report_id', 'sample_id', 'was_present',
        'overlap_start', 'overlap_end', 'evidence',
        'decision', 'decision_note', 'decided_by', 'decided_at', 'recheck_deadline',
    ];

    protected $casts = [
        'overlap_start' => 'datetime',
        'overlap_end' => 'datetime',
        'decided_at' => 'datetime',
        'recheck_deadline' => 'date',
        'evidence' => 'array',
        'was_present' => 'boolean',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('H:i:s') === '00:00:00'
            ? $date->format('Y-m-d')
            : $date->format('Y-m-d H:i:s');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ContaminationReport::class, 'contamination_report_id');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

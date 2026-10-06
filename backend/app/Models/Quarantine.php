<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quarantine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_id', 'contamination_report_id',
        'started_at', 'ended_at', 'started_by', 'ended_by', 'release_note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ContaminationReport::class);
    }
}

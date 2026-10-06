<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContaminationReport extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'report_no', 'incubator_id', 'suspect_sample_id',
        'suspected_start_at', 'suspected_end_at',
        'description', 'status', 'reported_by', 'reviewed_by', 'reviewed_at', 'decision_note',
    ];

    protected $casts = [
        'suspected_start_at' => 'datetime',
        'suspected_end_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }

    public function suspectSample(): BelongsTo
    {
        return $this->belongsTo(Sample::class, 'suspect_sample_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function exposures(): HasMany
    {
        return $this->hasMany(Exposure::class);
    }
}

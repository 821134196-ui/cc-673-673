<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasFactory;

    public const TYPE_RECHECK_DUE = 'recheck_due_today';
    public const TYPE_RECHECK_OVERDUE = 'recheck_overdue';

    protected $fillable = [
        'sample_id', 'type', 'channel', 'title', 'body',
        'remind_date', 'sent_at', 'read_at', 'dedupe_key',
    ];

    protected $casts = [
        'remind_date' => 'date',
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('H:i:s') === '00:00:00'
            ? $date->format('Y-m-d')
            : $date->format('Y-m-d H:i:s');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }
}

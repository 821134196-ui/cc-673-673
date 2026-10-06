<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRequest extends Model
{
    use HasFactory;

    public const STATUS_APPROVED = 'approved';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'sample_id', 'experiment_name', 'status', 'block_reason', 'requested_by',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

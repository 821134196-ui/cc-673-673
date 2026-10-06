<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_id', 'incubator_id', 'stored_at', 'removed_at', 'note', 'registered_by',
    ];

    protected $casts = [
        'stored_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function incubator(): BelongsTo
    {
        return $this->belongsTo(Incubator::class);
    }
}

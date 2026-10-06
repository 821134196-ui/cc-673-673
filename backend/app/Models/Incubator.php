<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incubator extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'location'];

    /** @return HasMany<StorageEvent> */
    public function storageEvents(): HasMany
    {
        return $this->hasMany(StorageEvent::class);
    }
}

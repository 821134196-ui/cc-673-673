<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incubator extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'location', 'description'];

    public function storageRecords(): HasMany
    {
        return $this->hasMany(StorageRecord::class);
    }

    public function contaminationReports(): HasMany
    {
        return $this->hasMany(ContaminationReport::class);
    }
}

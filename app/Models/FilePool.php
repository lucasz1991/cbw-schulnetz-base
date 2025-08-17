<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FilePool extends Model
{
    protected $fillable = [
        'title',
        'type',
        'description',
    ];

    /**
     * Polymorphe Beziehung z. B. zu User, Course, etc.
     */
    public function filepoolable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Alle Dateien in diesem Pool
     */
    public function files(): MorphMany
    {
        return $this->morphMany(\App\Models\File::class, 'fileable');
    }
}

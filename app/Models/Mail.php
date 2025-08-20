<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Jobs\ProcessMailJob;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Mail extends Model
{
    protected $fillable = [
        'type', 'status', 'content', 'recipients'
    ];

    protected $casts = [
        'content' => 'json',
        'recipients' => 'json',
    ];

        // Event-Listener für das "created"-Ereignis
    protected static function boot()
    {
        parent::boot();

        static::created(function ($mail) {
            // Dispatch Job zur Verarbeitung der Mail
            ProcessMailJob::dispatch($mail);
        });
    }

    /**
     * Alle Dateien in diesem Pool
     */
    public function files(): MorphMany
    {
        return $this->morphMany(\App\Models\File::class, 'fileable');
    }
}

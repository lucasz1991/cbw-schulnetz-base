<?php

namespace App\Models;

use App\Jobs\ProcessMailJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Mail extends Model
{
    protected $fillable = [
        'type', 'status', 'content', 'recipients',
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
            // Auch die synchrone Queue darf erst nach dem Datenbank-Commit senden.
            $mail->getConnection()->afterCommit(fn () => ProcessMailJob::dispatch($mail));
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

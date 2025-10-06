<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','type','title','message',
        'date_from','date_to','attachment_path',
        'status','submitted_at','decided_at','admin_comment',

        // neu
        'class_code','institute','participant_no',
        'original_exam_date','scheduled_at','module_code','instructor_name',
        'full_day','time_arrived_late','time_left_early',
        'reason','reason_item','with_attest','fee_cents',
        'exam_modality','certification_key','certification_label',
        'class_label','email_priv','data',
    ];

    protected $casts = [
        'date_from'          => 'date',
        'date_to'            => 'date',
        'submitted_at'       => 'datetime',
        'decided_at'         => 'datetime',
        'original_exam_date' => 'date',
        'scheduled_at'       => 'datetime',
        'full_day'           => 'boolean',
        'with_attest'        => 'boolean',
        'fee_cents'          => 'integer',
        'data'               => 'array',
    ];

    // Komfort-Accessors
    public function getFeeFormattedAttribute(): ?string
    {
        return is_null($this->fee_cents) ? null : number_format($this->fee_cents / 100, 2, ',', '.') . ' €';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

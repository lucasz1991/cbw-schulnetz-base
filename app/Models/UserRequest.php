<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\AdminTask;
use App\Models\Setting;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewUserRequestNotification;


class UserRequest extends Model
{
    use HasFactory;
    use Notifiable;
    /**
     * -------------------------------------------------------------------------
     *  Typen und Status (optionale Konstanten)
     * -------------------------------------------------------------------------
     */
    public const TYPE_ABSENCE         = 'absence';
    public const TYPE_MAKEUP          = 'makeup';
    public const TYPE_EXTERNAL_MAKEUP = 'external_makeup';
    public const TYPE_GENERAL         = 'general';



    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELED  = 'canceled';
    public const STATUS_IN_REVIEW = 'in_review';

    /**
     * -------------------------------------------------------------------------
     *  Massenweise befüllbare Felder
     * -------------------------------------------------------------------------
     */
    protected $fillable = [
        'user_id',
        'type',
        'class_code',
        'institute',
        'participant_no',
        'title',
        'message',
        'date_from',
        'date_to',
        'original_exam_date',
        'scheduled_at',
        'module_code',
        'instructor_name',
        'full_day',
        'time_arrived_late',
        'time_left_early',
        'reason',
        'reason_item',
        'with_attest',
        'fee_cents',
        'exam_modality',
        'certification_key',
        'certification_label',
        'class_label',
        'email_priv',
        'attachment_path',
        'status',
        'submitted_at',
        'decided_at',
        'admin_comment',
        'data',
    ];

    /**
     * -------------------------------------------------------------------------
     *  Casts
     * -------------------------------------------------------------------------
     */
    protected $casts = [
        'date_from'          => 'date',
        'date_to'            => 'date',
        'original_exam_date' => 'date',
        'scheduled_at'       => 'datetime',
        'submitted_at'       => 'datetime',
        'decided_at'         => 'datetime',
        'full_day'           => 'boolean',
        'with_attest'        => 'boolean',
        'fee_cents'          => 'integer',
        'data'               => AsArrayObject::class,
    ];


    protected static function booted(): void
    {
        // deine bisherigen Defaults beim Erzeugen
        static::created(function (UserRequest $request) {
            $request->notifyAdminIfEnabled();
            AdminTask::create([
                'created_by'   => $request->user_id,
                'context_type' => UserRequest::class,
                'context_id'   => $request->id,
                'task_type'    => 'user_request_review',
                'description'  => "Teilnehmerantrag {$request->title} von {$request->user->name} eingereicht – Prüfung & Freigabe erforderlich.",
                'status'       => AdminTask::STATUS_OPEN,
            ]);
        });
    }
    /**
     * -------------------------------------------------------------------------
     *  Beziehungen
     * -------------------------------------------------------------------------
     */

    /** Antragsteller */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Optionale Dateien (polymorph, standardisiert im Projekt) */
    public function files(): MorphMany
    {
        return $this->morphMany(\App\Models\File::class, 'fileable');
    }

    /**
     * -------------------------------------------------------------------------
     *  Accessors & Helper
     * -------------------------------------------------------------------------
     */

    /** Formatierte Gebühr (z. B. 20,00 €) */
    public function getFeeFormattedAttribute(): ?string
    {
        return is_null($this->fee_cents)
            ? null
            : number_format($this->fee_cents / 100, 2, ',', '.') . ' €';
    }

    /** Anzeige für „mit/ohne Attest“ */
    public function getWithAttestLabelAttribute(): string
    {
        return $this->with_attest ? 'mit Attest' : 'ohne Attest';
    }

    /** Kurze Statusbeschreibung */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED  => 'Genehmigt',
            self::STATUS_REJECTED  => 'Abgelehnt',
            self::STATUS_CANCELED  => 'Storniert',
            self::STATUS_IN_REVIEW => 'In Prüfung',
            default                => 'Eingereicht',
        };
    }


    /** Kurze Typ-Beschreibung */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ABSENCE         => 'Fehlzeit Meldung',
            self::TYPE_MAKEUP          => 'Nachholtermin Anfrage',
            self::TYPE_EXTERNAL_MAKEUP => 'Externer Nachholtermin',
            self::TYPE_GENERAL         => 'Allgemeine Anfrage',
            default                    => 'Sonstiger Antrag',
        };
    }
    /**
     * -------------------------------------------------------------------------
     *  Scopes / Query Helpers
     * -------------------------------------------------------------------------
     */

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('submitted_at');
    }

    /**
     * -------------------------------------------------------------------------
     *  Business-Methoden
     * -------------------------------------------------------------------------
     */

    public function approve(?string $adminComment = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_APPROVED,
            'decided_at' => now(),
            'admin_comment' => $adminComment,
        ])->save();
    }

    public function reject(?string $adminComment = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_REJECTED,
            'decided_at' => now(),
            'admin_comment' => $adminComment,
        ])->save();
    }

    public function cancel(?string $adminComment = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELED,
            'decided_at' => now(),
            'admin_comment' => $adminComment,
        ])->save();
    }
    // -------------------------------------------------------------------------
    // Admin-E-Mail Notification Helper
    // -------------------------------------------------------------------------

    /**
     * Prüft Settings und schickt ggf. eine NewUserRequestNotification
     * an die hinterlegte Admin-E-Mail.
     */
    public function notifyAdminIfEnabled(): void
    {
        // 1. Ist die Benachrichtigung für User-Requests aktiviert?
        if (! $this->isNewUserRequestNotificationEnabled()) {
            return;
        }

        // 2. Admin-E-Mail aus Settings holen
        $adminEmail = $this->getAdminEmailFromSettings();

        if (! $adminEmail) {
            return;
        }

        // 3. Notification verschicken (ohne echten User, nur Route)
        Notification::route('mail', $adminEmail)
            ->notify(new NewUserRequestNotification($this));
    }

    /**
     * Liest die Admin-E-Mail aus den Mail-Settings.
     *
     * Erwartet Setting:
     *   type = 'mails'
     *   key  = 'admin_email'
     */
    protected function getAdminEmailFromSettings(): ?string
    {
        return Setting::where('type', 'mails')
            ->where('key', 'admin_email')
            ->value('value');
    }

    /**
     * Prüft, ob das Notification-Setting für neue User-Requests gesetzt ist.
     *
     * Erwartet Setting:
     *   type = 'admin_notifications'
     *   key  = 'new_user_request'
     * und ein truthy value (1, true, "on", "yes", "true").
     */
    protected function isNewUserRequestNotificationEnabled(): bool
    {
        $raw = Setting::where('type', 'mails')
            ->where('key', 'new_user_request')
            ->value('value');

        if ($raw === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $raw));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
    
}

<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Message;
use App\Models\Person;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Notifications\CustomResetPasswordNotification;
use App\Models\Course;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Models\CourseRating;
use App\Models\UserRequest;
use App\Models\ReportBook;
use App\Models\ReportBookEntry;



class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'email', 'password','role', 'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function person()
    {
        return $this->hasOne(Person::class, 'user_id');
    }
    
    public function receivedMessages()
    {
        return  $this->hasMany(Message::class, 'to_user')->where('to_user', $this->id);
    }
    public function receivedUnreadMessages()
    {   
        $unreadmessages = $this->receivedMessages()->where('status',1);
        return $unreadmessages;
    }

        /**
     * Sende eine Nachricht an einen anderen Benutzer.
     *
     * @param int $toUserId
     * @param string $subject
     * @param string $message
     * @return void
     */
    public function sendMessage($toUserId, $subject, $message)
    {
        Message::create([
            'subject' => $subject,
            'message' => $message,
            'from_user' => $this->id, 
            'to_user' => $toUserId,
            'status' => '1',
        ]);
    }

    public function receiveMessage($subject, $message, $fromUserId = null, $files = null)
    {
        $message = Message::create([
            'subject' => $subject,
            'message' => $message,
            'from_user' => 1, 
            'to_user' => $this->id,
            'status' => '1',
        ]);
        if ($files) {
            foreach ($files as $file) {
                $message->files()->create([
                    'name' => $file->name,
                    'path' => $file->path,
                    'mime_type' => $file->mime_type,
                    'size' => $file->size,
                    'expires_at' => $file->expires_at ?? null,
                ]);
            }
        }
        return $message;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status;
    }





 
    
    public function sendEmailVerificationNotification()
    {
        try {
            // Überprüfung, ob die E-Mail-Adresse gültig ist (optional)
            if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Ungültige E-Mail-Adresse: " . $this->email);
            }
    
            $this->notify(new CustomVerifyEmail);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            Log::error('Transport-Fehler beim Senden der E-Mail: ' . $e->getMessage());
            session()->flash('error', 'Die E-Mail konnte nicht zugestellt werden. Bitte überprüfen Sie Ihre E-Mail-Adresse.');
        } catch (\Symfony\Component\Mailer\Exception\UnexpectedResponseException $e) {
            Log::error('Unerwartete Antwort vom Mailserver: ' . $e->getMessage());
            session()->flash('error', 'Die E-Mail konnte nicht zugestellt werden. Bitte wenden Sie sich an den Support.');
        } catch (\Exception $e) {
            Log::error('Allgemeiner Fehler beim Senden der E-Mail: ' . $e->getMessage());
            session()->flash('error', 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
        }
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($this, $token));
    }


    public function hasAccessToInvoice($filename)
    {
        // Extrahiere die Benutzer-ID aus dem Dateinamen (z. B. "1_Doe_rental_bill_12345_date_2024_12_15.pdf")
        if (preg_match('/^(\d+)_/', $filename, $matches)) {
            $userIdFromFilename = $matches[1];

            // Prüfe, ob die Benutzer-ID mit der aktuellen Benutzer-ID übereinstimmt
            return $this->id == $userIdFromFilename;
        }

        return false; // Zugriff verweigern, wenn der Dateiname nicht das richtige Format hat
    }

    public function ratings()
    {
        return $this->hasMany(CourseRating::class, 'user_id');
    }

    public function userRequests()
    {
        return $this->hasMany(UserRequest::class);
    }

    public function filePool(): MorphOne
    {
        return $this->morphOne(FilePool::class, 'filepoolable');
    }

    /**
     * Alle Berichtshefte des Users (z. B. je Maßnahme eins)
     */
    public function reportBooks()
    {
        return $this->hasMany(ReportBook::class);
    }

    /**
     * Komfort-Helper: Berichtsheft zu einer bestimmten Maßnahme holen
     */
    public function reportBookFor(?string $massnahmeId)
    {
        return $this->reportBooks()
            ->where('massnahme_id', $massnahmeId)
            ->first();
    }

    /**
     * Alle Berichtsheft-Einträge des Users (über alle Maßnahmen)
     */
    public function reportBookEntries()
    {
        return $this->hasManyThrough(
            ReportBookEntry::class,
            ReportBook::class,
            'user_id',          // FK auf ReportBook -> users.id
            'report_book_id',   // FK auf ReportBookEntry -> report_books.id
            'id',               // Local key users.id
            'id'                // Local key report_books.id
        );
    }

    /**
     * Neuester Eintrag (über alle Maßnahmen)
     */
    public function latestReportBookEntry()
    {
        return $this->reportBookEntries()
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->first();
    }
}

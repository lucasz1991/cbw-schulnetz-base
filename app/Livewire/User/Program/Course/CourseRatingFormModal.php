<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\CourseRating;

class CourseRatingFormModal extends Component
{
    /** Modal sichtbar? */
    public bool $showModal = false;

    /** Kontext */
    public int $courseId;
    public ?int $classId = null;
    public ?int $tutorId = null;

    /** Kopf-Anzeige (readonly) */
    public string $klasse = '';
    public string $baustein = '';
    public string $dozent = '';

    /** Formfelder */
    public bool $is_anonymous = false;
    public ?int $kb_1 = null;
    public ?int $kb_2 = null;
    public ?int $kb_3 = null;
    public ?int $sa_1 = null;
    public ?int $sa_2 = null;
    public ?int $sa_3 = null;
    public ?int $il_1 = null;
    public ?int $il_2 = null;
    public ?int $il_3 = null;
    public ?int $do_1 = null;
    public ?int $do_2 = null;
    public ?int $do_3 = null;
    public string $message = '';

    /** Bereits bewertet? */
    public bool $alreadyRated = false;

    public int $currentStep = 1; // 1..5

    protected $listeners = [
        'open-course-rating-modal' => 'open',
    ];

    public function rules(): array
    {
        return [
            'kb_1' => 'required|integer|min:1|max:5',
            'kb_2' => 'required|integer|min:1|max:5',
            'kb_3' => 'required|integer|min:1|max:5',
            'sa_1' => 'required|integer|min:1|max:5',
            'sa_2' => 'required|integer|min:1|max:5',
            'sa_3' => 'required|integer|min:1|max:5',
            'il_1' => 'required|integer|min:1|max:5',
            'il_2' => 'required|integer|min:1|max:5',
            'il_3' => 'required|integer|min:1|max:5',
            'do_1' => 'required|integer|min:1|max:5',
            'do_2' => 'required|integer|min:1|max:5',
            'do_3' => 'required|integer|min:1|max:5',
            'message' => 'nullable|string|max:500',
            'is_anonymous' => 'boolean',
        ];
    }

    public function open(array $payload = []): void
    {
        $this->resetValidation();

        $this->courseId = (int) ($payload['course_id'] ?? 0);
        $this->classId  = $payload['class_id'] ?? null;
        $this->tutorId  = $payload['tutor_id'] ?? null;

        $this->klasse   = $payload['klasse']   ?? '';
        $this->baustein = $payload['baustein'] ?? '';
        $this->dozent   = $payload['dozent']   ?? '';

        $this->prefillIfExisting();
        $this->showModal = true;
    }

    public function updatedShowModal($open)
    {
        if ($open) {
            $this->currentStep = 1; // Reset beim Öffnen
        }
    }

    protected function prefillIfExisting(): void
    {
        $participantId = Auth::id();

        $existing = CourseRating::where('course_id', $this->courseId)
            ->when($this->classId, fn($q) => $q->where('class_id', $this->classId))
            ->where('participant_id', $participantId)
            ->first();

        $this->alreadyRated = (bool) $existing;

        if ($existing) {
            $this->fill($existing->only([
                'kb_1','kb_2','kb_3',
                'sa_1','sa_2','sa_3',
                'il_1','il_2','il_3',
                'do_1','do_2','do_3',
                'message','is_anonymous'
            ]));
        }
    }

    public function save(): void
    {
        if ($this->alreadyRated) {
            $this->dispatch('toast', type:'info', message:'Sie haben diesen Baustein bereits bewertet.');
            return;
        }

        $this->validate();

        $participant = Auth::user();

        CourseRating::create([
            'user_id'        => Auth::id(),
            'course_id'      => $this->courseId,
            'class_id'       => $this->classId,
            'tutor_id'       => $this->tutorId,
            'participant_id' => $this->is_anonymous ? null : ($participant?->id),

            'kb_1' => $this->kb_1, 'kb_2' => $this->kb_2, 'kb_3' => $this->kb_3,
            'sa_1' => $this->sa_1, 'sa_2' => $this->sa_2, 'sa_3' => $this->sa_3,
            'il_1' => $this->il_1, 'il_2' => $this->il_2, 'il_3' => $this->il_3,
            'do_1' => $this->do_1, 'do_2' => $this->do_2, 'do_3' => $this->do_3,

            'message' => trim($this->message) ?: null,
        ]);

        $this->alreadyRated = true;
        $this->dispatch('toast', type:'success', message:'Bewertung erfolgreich gespeichert.');
        $this->dispatch('refreshParent');
    }

    public function render()
    {
        return view('livewire.user.program.course.course-rating-form-modal');
    }
}

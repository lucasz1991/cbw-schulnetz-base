<?php

namespace App\Livewire\User\Program\Course;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\CourseRating;
use Illuminate\Validation\ValidationException;
use App\Models\Course;
use App\Models\CourseDay;
use Livewire\Attributes\On;

class CourseRatingFormModal extends Component
{
    /** Modal sichtbar? */
    public bool $showModal = false;

    /** Required-Flag: Modal darf nicht einfach geschlossen werden */
    public bool $isRequired = false;

    /** Kontext */
    public $courseId;
    public $course;
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
        'open-course-rating-modal'          => 'open',
        'open-course-rating-required-modal' => 'openRequired',
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

    public function messages(): array
{
    $between = 'Bitte wählen Sie eine Bewertung zwischen 1 (sehr schlecht) und 5 (sehr gut).';

    return [
        // Kundenbetreuung
        'kb_1.required' => 'Bitte bewerten Sie die Kompetenz der Kundenbetreuung.',
        'kb_1.integer'  => $between,
        'kb_1.min'      => $between,
        'kb_1.max'      => $between,

        'kb_2.required' => 'Bitte bewerten Sie, ob Ihre Probleme in der Kundenbetreuung ernst genommen und zeitnah erledigt werden.',
        'kb_2.integer'  => $between,
        'kb_2.min'      => $between,
        'kb_2.max'      => $between,

        'kb_3.required' => 'Bitte bewerten Sie, ob die Mitarbeiter/-innen der Kundenbetreuung freundlich und höflich sind.',
        'kb_3.integer'  => $between,
        'kb_3.min'      => $between,
        'kb_3.max'      => $between,

        // Systemadministration
        'sa_1.required' => 'Bitte bewerten Sie die Kompetenz der Systemadministration.',
        'sa_1.integer'  => $between,
        'sa_1.min'      => $between,
        'sa_1.max'      => $between,

        'sa_2.required' => 'Bitte bewerten Sie, ob Ihre Probleme in der Systemadministration ernst genommen und zeitnah erledigt werden.',
        'sa_2.integer'  => $between,
        'sa_2.min'      => $between,
        'sa_2.max'      => $between,

        'sa_3.required' => 'Bitte bewerten Sie, ob die Mitarbeiter/-innen der Systemadministration freundlich und höflich sind.',
        'sa_3.integer'  => $between,
        'sa_3.min'      => $between,
        'sa_3.max'      => $between,

        // Institutsleitung
        'il_1.required' => 'Bitte bewerten Sie die Organisation im Institut.',
        'il_1.integer'  => $between,
        'il_1.min'      => $between,
        'il_1.max'      => $between,

        'il_2.required' => 'Bitte bewerten Sie, ob Ihre Anliegen bei der Institutsleitung ernst genommen und zeitnah bearbeitet werden.',
        'il_2.integer'  => $between,
        'il_2.min'      => $between,
        'il_2.max'      => $between,

        'il_3.required' => 'Bitte bewerten Sie, ob die Mitarbeiter/-innen der Institutsleitung freundlich und höflich sind.',
        'il_3.integer'  => $between,
        'il_3.min'      => $between,
        'il_3.max'      => $between,

        // Dozent/-in
        'do_1.required' => 'Bitte bewerten Sie, ob der/die Dozent/-in Ihnen gegenüber freundlich und höflich war.',
        'do_1.integer'  => $between,
        'do_1.min'      => $between,
        'do_1.max'      => $between,

        'do_2.required' => 'Bitte bewerten Sie die Fachkompetenz des/der Dozent/-in.',
        'do_2.integer'  => $between,
        'do_2.min'      => $between,
        'do_2.max'      => $between,

        'do_3.required' => 'Bitte bewerten Sie die methodischen und didaktischen Fähigkeiten des/der Dozent/-in.',
        'do_3.integer'  => $between,
        'do_3.min'      => $between,
        'do_3.max'      => $between,

        // Freitext
        'message.max'   => 'Ihre Nachricht darf maximal 500 Zeichen enthalten.',

        // Checkbox
        'is_anonymous.boolean' => 'Die Angabe zur anonymen Bewertung ist ungültig.',
    ];
}


    public function getBlocksProperty()
    {
        return [
            1 => ['title' => 'Kundenbetreuung', 'rows' => [
                'kb_1' => 'Wie kompetent sind die Mitarbeiter/-innen der Kundenbetreuung?',
                'kb_2' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                'kb_3' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
            ]],
            2 => ['title' => 'Systemadministration', 'rows' => [
                'sa_1' => 'Wie kompetent sind die Mitarbeiter/-innen der Systemadministration?',
                'sa_2' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                'sa_3' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
            ]],
            3 => ['title' => 'Institutsleitung', 'rows' => [
                'il_1' => 'Wie beurteilen Sie die Organisation im Institut?',
                'il_2' => 'Werden Ihre Probleme ernst genommen und zeitnah erledigt?',
                'il_3' => 'Sind die Mitarbeiter/-innen freundlich und höflich?',
            ]],
            4 => ['title' => 'Dozent/-in', 'rows' => [
                'do_1' => 'War der/die Dozent/-in Ihnen gegenüber freundlich und höflich?',
                'do_2' => 'Wie beurteilen Sie die Fachkompetenz?',
                'do_3' => 'Wie beurteilen Sie die methodischen und didaktischen Fähigkeiten?',
            ]],
        ];
    }


    public function open(array $payload = []): void
    {
        $this->resetValidation();

        // „Normaler“ Modus: nicht erzwungen
        $this->isRequired = false;

        $this->courseId = $payload['course_id'] ?? null;
        $this->course   = Course::where('klassen_id', $this->courseId)->first();
        if (!$this->course) {
            return;
        }
        $this->classId  = $payload['class_id'] ?? null;
        $this->tutorId  = $payload['tutor_id'] ?? null;

        $this->klasse   = $payload['klasse']   ?? '';
        $this->baustein = $payload['baustein'] ?? '';
        $this->dozent   = $payload['dozent']   ?? '';

        $this->prefillIfExisting();
        $this->showModal = true;
    }

    /**
     * Required-Variante: kann nicht „einfach so“ geschlossen werden,
     * solange noch keine Bewertung existiert.
     */

    #[On('open-course-rating-required-modal')]
    public function openRequired(array $payload = []): void
    {
        $this->resetValidation();

        $this->isRequired = true;
        $this->courseId = $payload['course_id'] ?? null;
        $this->course   = Course::where('klassen_id', $this->courseId)->first();
        if (!$this->course) {
            // Kurs nicht gefunden: Required-Modus deaktivieren
            $this->isRequired = false;
            return;
        }
        $this->classId  = $payload['class_id'] ?? null;
        $this->tutorId  = $payload['tutor_id'] ?? null;

        $this->klasse   = $payload['klasse']   ?? '';
        $this->baustein = $payload['baustein'] ?? '';
        $this->dozent   = $payload['dozent']   ?? '';

        $this->prefillIfExisting();
        $this->showModal = true;
    }

    public function updatedShowModal($open): void
    {
        if ($open) {
            // Reset Step beim Öffnen
            $this->currentStep = 1;
            return;
        }

        // Versucht zu schließen:
        // Wenn required & noch keine Bewertung => direkt wieder öffnen
        if ($this->isRequired && !$this->alreadyRated) {
            $this->showModal = true;
        }
    }

    protected function prefillIfExisting(): void
    {
        $participantId = Auth::id();

        $existing = CourseRating::where('course_id', $this->course?->id)
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

    protected function setStepFromErrors(array $errorKeys): void
    {
        $firstField = $errorKeys[0] ?? null;
        if (!$firstField) {
            return;
        }

        // Standard: Step 1
        $step = 1;

        // Über die Blocks laufen und schauen, wo das Feld drin ist
        foreach ($this->blocks as $idx => $block) {
            if (array_key_exists($firstField, $block['rows'])) {
                $step = $idx;
                break;
            }
        }

        // Spezieller Fall: Nachricht im letzten Step
        if ($firstField === 'message') {
            $step = 5;
        }

        $this->currentStep = $step;
    }


    public function save(): void
    {
        if ($this->alreadyRated) {
            $this->dispatch('toast', type:'info', message:'Sie haben diesen Baustein bereits bewertet.');
            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            // Step auf den ersten Fehler setzen
            $this->setStepFromErrors($e->validator->errors()->keys());

            // Exception normal weiterwerfen, damit Livewire die Fehler anzeigt
            throw $e;
        }

        $participant = Auth::user();

        CourseRating::create([
            'user_id'        => Auth::id(),
            'course_id'      => $this->course->id,
            'is_anonymous'   => $this->is_anonymous,
            'participant_id' => $participant?->id,

            'kb_1' => $this->kb_1, 'kb_2' => $this->kb_2, 'kb_3' => $this->kb_3,
            'sa_1' => $this->sa_1, 'sa_2' => $this->sa_2, 'sa_3' => $this->sa_3,
            'il_1' => $this->il_1, 'il_2' => $this->il_2, 'il_3' => $this->il_3,
            'do_1' => $this->do_1, 'do_2' => $this->do_2, 'do_3' => $this->do_3,

            'message' => trim($this->message) ?: '',
        ]);

        $this->alreadyRated = true;

        // Required-Modus beenden & Modal schließen
        $this->isRequired = false;
        $this->showModal  = false;

        $this->dispatch('toast', type:'success', message:'Bewertung erfolgreich gespeichert.');
        $this->dispatch('refreshParent');
    }

    public function render()
    {
        return view('livewire.user.program.course.course-rating-form-modal', [
            'blocks' => $this->blocks,
        ]);
    }
}

<?php

namespace App\Livewire\Coaching;

use App\Models\CoachingContract;
use App\Services\Coaching\{Access, PlanService, ScheduleGenerator, ScheduleReview};
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Planning extends Component
{
    #[Locked] public ?int $contractId = null;
    #[Locked] public int $revision = 0;
    public array $items = [];
    public string $message = '';
    public bool $editing = false;
    #[Locked] public int $editorStep = 1;
    #[Locked] public string $planView = 'list';
    #[Locked] public string $calendarMonth = '';
    #[Locked] public string $calendarDate = '';
    public ?string $expandedSlotId = null;
    public bool $composing = false;
    public array $schedule = ['start_date' => '', 'weekdays' => [1, 3], 'start_time' => '09:00',
        'units_per_appointment' => 2, 'every_weeks' => 1, 'topic' => '', 'format' => 'online', 'location' => 'Online'];
    public ?string $generationStatus = null;

    public function mount(): void
    {
        abort_unless(Access::available(), 404);
        if (request()->has('contract')) {
            $data = request()->validate(['contract' => 'required|integer|min:1']);
            $this->select((int)$data['contract']); // select/reload checks ownership, also for inbox deep links.
        }
    }

    private function contract(): CoachingContract
    {
        abort_unless(Access::available(), 404);
        return CoachingContract::forUser(auth()->user())->with(['latestPlan', 'participant', 'tutor', 'course'])->findOrFail($this->contractId);
    }

    public function select(int $id): void
    {
        $this->contractId = $id;
        $this->reload();
    }

    public function reload(): void
    {
        $contract = $this->contract();
        $this->revision = $contract->revision;
        $this->items = $contract->latestPlan?->items ?? [];
        $this->editing = false;
        $this->editorStep = 1;
        $this->resetReview();
        $this->composing = false;
        $this->message = '';
        $this->generationStatus = null;
        $this->resetValidation();
    }

    public function edit(): void
    {
        $contract = $this->editableContract();
        $this->resetValidation();
        $this->items = $contract->latestPlan?->items ?? [];
        $this->generationStatus = null;
        $first = $this->items[0] ?? [];
        $earliest = max(now('Europe/Berlin')->addDay()->toDateString(), $contract->valid_from?->toDateString() ?? '');
        $this->schedule = ['start_date' => max($first['date'] ?? '', $earliest), 'weekdays' => [1, 3],
            'start_time' => $first['start'] ?? '09:00', 'units_per_appointment' => min(2, max(1, intdiv(720, $contract->unit_minutes))),
            'every_weeks' => 1, 'topic' => $first['topic'] ?? $contract->title,
            'format' => $first['format'] ?? 'online', 'location' => $first['location'] ?? 'Online'];
        $this->editing = true;
        $this->editorStep = 1;
        $this->resetReview();
        $this->composing = false;
        if (!$this->items) $this->add();
    }

    private function editableContract(): CoachingContract
    {
        $contract = $this->contract();
        app(PlanService::class)->actor($contract, auth()->user());
        abort_if($contract->confirmed_plan_id || !$contract->activeOn() || $contract->cancelled_on, 403);
        if ($contract->revision !== $this->revision) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plan' => 'Der Gesamtplan wurde inzwischen geändert. Bitte schließen und neu laden.']);
        }
        return $contract;
    }

    public function generateSchedule(): void
    {
        $contract = $this->editableContract();
        abort_unless($this->editing, 403);
        $this->resetValidation();
        $items = app(ScheduleGenerator::class)->generate($contract, $this->schedule);
        $this->items = $items;
        $this->resetReview();
        $this->editorStep = 2;
        $this->generationStatus = count($items).' Termine · '.$contract->agreed_minutes.' Minuten vollständig verteilt.';
    }

    public function cancelEditing(): void { $this->reload(); }

    private function resetReview(): void
    {
        $this->planView = 'list';
        $this->calendarMonth = $this->calendarDate = '';
        $this->expandedSlotId = null;
    }

    public function changePlanView(string $view): void
    {
        $this->editableContract();
        abort_unless($this->editing && in_array($view, ['list', 'calendar'], true), 403);
        $this->planView = $view;
        $this->expandedSlotId = null;
    }

    public function movePlanMonth(int $direction): void
    {
        $this->editableContract();
        abort_unless($this->editing && in_array($direction, [-1, 1], true), 403);
        $calendar = ScheduleReview::calendar($this->items, $this->calendarMonth, $this->calendarDate);
        $month = ScheduleReview::date($calendar['month'].'-01')->addMonths($direction);
        $this->calendarMonth = $month->format('Y-m');
        $dates = array_values(array_filter(array_column($this->items, 'date'), fn ($date) => str_starts_with($date, $this->calendarMonth) && ScheduleReview::date($date)));
        sort($dates);
        $this->calendarDate = $dates[0] ?? $month->toDateString();
        $this->expandedSlotId = null;
    }

    public function selectPlanDate(string $date): void
    {
        $this->editableContract();
        abort_unless($this->editing && ScheduleReview::date($date), 403);
        $this->calendarDate = $date;
        $this->calendarMonth = substr($date, 0, 7);
        $this->expandedSlotId = null;
    }

    public function toggleSlot(string $id): void
    {
        $this->editableContract();
        abort_unless($this->editing && in_array($id, array_column($this->items, 'id'), true), 403);
        $this->expandedSlotId = $this->expandedSlotId === $id ? null : $id;
    }

    public function scheduleSettings(): void
    {
        $this->editableContract();
        abort_unless($this->editing, 403);
        $this->resetValidation();
        $this->editorStep = 1;
    }

    public function reviewSchedule(): void
    {
        $this->editableContract();
        abort_unless($this->editing, 403);
        $this->resetValidation();
        $this->editorStep = 2;
    }

    public function submitEditor(): void
    {
        if ($this->editorStep === 1) $this->generateSchedule();
        else $this->propose();
    }

    public function composeMessage(): void
    {
        abort_unless($this->contract()->activeOn(), 403);
        $this->resetValidation();
        $this->composing = true;
    }

    public function cancelMessage(): void
    {
        $this->composing = false;
        $this->message = '';
        $this->resetValidation();
    }

    public function add(): void
    {
        $this->editableContract();
        $this->generationStatus = null;
        if (count($this->items) >= 255) return;
        $last = end($this->items) ?: [];
        $date = $last['date'] ?? '';
        $this->items[] = ['id' => (string)Str::uuid(),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) ? \Carbon\Carbon::parse($date)->addWeek()->toDateString() : '',
            'start' => $last['start'] ?? '09:00', 'end' => $last['end'] ?? '10:30',
            'topic' => $last['topic'] ?? '', 'format' => $last['format'] ?? 'online', 'location' => $last['location'] ?? 'Online'];
        if ($this->editorStep === 2) {
            $this->expandedSlotId = end($this->items)['id'];
            $this->focusDraftDate(end($this->items)['date']);
        }
    }

    public function remove(int $index): void
    {
        $this->editableContract();
        if (($this->items[$index]['id'] ?? null) === $this->expandedSlotId) $this->expandedSlotId = null;
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->generationStatus = null;
    }

    public function updatedItems($value = null, $key = null): void
    {
        $this->generationStatus = null;
        if (is_string($key) && str_ends_with($key, '.date')) $this->focusDraftDate((string)$value);
    }

    private function focusDraftDate(string $date): void
    {
        if ($this->planView === 'calendar' && ScheduleReview::date($date)) {
            $this->calendarDate = $date;
            $this->calendarMonth = substr($date, 0, 7);
        }
    }

    public function propose(): void
    {
        abort_unless($this->editing && $this->editorStep === 2, 403);
        try {
            app(PlanService::class)->propose($this->contract()->id, auth()->user(), $this->revision, $this->items);
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->planView = 'list';
            foreach (array_keys($error->errors()) as $key) {
                if (preg_match('/^items\.(\d+)/', $key, $match)) { $this->expandedSlotId = $this->items[(int)$match[1]]['id'] ?? null; break; }
            }
            throw $error;
        }
        $this->reload();
        session()->flash('coaching_status', 'Gesamtplan gespeichert. Beide Seiten müssen diese Version bestätigen.');
    }

    public function confirm(): void
    {
        if ($this->editing) { $this->addError('plan', 'Bitte den bearbeiteten Gesamtplan zuerst speichern.'); return; }
        app(PlanService::class)->confirm($this->contract()->id, auth()->user(), $this->revision);
        $this->reload();
        session()->flash('coaching_status', 'Bestätigung gespeichert. Nach beiden Bestätigungen und UVS-Abgleich wird der Baustein freigegeben.');
    }

    public function sendMessage(): void
    {
        app(PlanService::class)->message($this->contract()->id, auth()->user(), $this->message);
        $this->message = '';
        $this->composing = false;
    }

    public function render()
    {
        abort_unless(Access::available(), 404);
        $contract = $this->contractId ? $this->contract() : null;
        return view('livewire.coaching.planning', [
            'contracts' => CoachingContract::forUser(auth()->user())->with(['participant', 'tutor'])->orderByDesc('id')->get(),
            'contract' => $contract, 'plan' => $contract?->latestPlan,
            'messages' => $contract?->messages()->with('author')->latest()->limit(50)->get()->reverse() ?? collect(),
            'actor' => $contract ? app(PlanService::class)->actor($contract, auth()->user()) : null,
        ])->layout(auth()->user()->role === 'tutor' ? 'layouts.app-tutor' : 'layouts.app');
    }
}

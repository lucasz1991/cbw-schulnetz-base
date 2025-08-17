@props([
    'dates' => collect(),
    'eventTitle' => 'Kurstermin',
    'dateField' => 'date',
    'startTimeField' => null,
    'endTimeField' => null,
    'selectedDayId' => null,
])

@php
    use Illuminate\Support\Carbon;

$events = collect($dates)->map(function ($item, $idx) use ($eventTitle, $dateField, $startTimeField, $endTimeField) {
    $dateValue = is_object($item) || is_array($item) ? data_get($item, $dateField) : $item;
    $dateStr   = $dateValue instanceof \DateTimeInterface
        ? $dateValue->format('Y-m-d')
        : \Illuminate\Support\Carbon::parse($dateValue)->format('Y-m-d');

    $startTime = $startTimeField ? data_get($item, $startTimeField) : null;
    $endTime   = $endTimeField   ? data_get($item, $endTimeField)   : null;

    // HIER: echte DB-ID verwenden (bei Eloquent-Modellen funktioniert data_get('id'))
    $eventId = data_get($item, 'id') ?? ($idx + 1);

    if ($startTime) {
        return [
            'id'     => (string) $eventId,   // als String ist sicher
            'title'  => '',
            'start'  => trim($dateStr.' '.$startTime),
            'end'    => $endTime ? trim($dateStr.' '.$endTime) : null,
            'allDay' => false,
        ];
    }

    return [
        'id'     => (string) $eventId,
        'title'  => '',
        'start'  => $dateStr,
        'allDay' => true,
    ];
})->values();
@endphp
<div x-data="{ width: 0 }">
    <div
        {{ $attributes->merge(['class' => 'show-dates-calendar bg-blue-100 p-4 rounded-lg ']) }}
        x-data="(() => {
            const eventsFromServer = @js($events);
    
            // Warten bis FullCalendar global vorhanden ist (unabhängig von Script-Load-Reihenfolge)
            const waitFor = (cond, cb, tries = 0) => {
                if (cond()) return cb();
                if (tries > 200) return console.error('FullCalendar konnte nicht geladen werden.');
                setTimeout(() => waitFor(cond, cb, tries + 1), 25);
            };
    
            return {
                calendar: null,
                events: eventsFromServer,
                selectedDayId: @entangle('selectedDayId').live,
                options: {
                    initialView: 'dayGridMonth',
                    locale: 'de',
                    timeZone: 'Europe/Berlin',
                    firstDay: 1,
                    height: 'auto',
                    selectable: false,
                    headerToolbar: { left: 'prev,next', center: '', right: 'title' },
                    buttonText: { today: 'Heute', month: 'Monat', week: 'Woche', day: 'Tag' },
                    businessHours: { daysOfWeek: [1,2,3,4,5], startTime: '08:00', endTime: '18:00' },
                },
                init() {
                    this.$watch('selectedDayId', () => {
                        if (this.calendar) this.calendar.render();
                    });
                    setTimeout(() => {
                        waitFor(() => window.FullCalendar && this.$refs.calendar, () => {
                            this.calendar = new FullCalendar.Calendar(this.$refs.calendar, {
                                ...this.options,
                                events: this.events,
                                eventClassNames: (arg) => {
                                    return String(arg.event.id) === String(this.selectedDayId)
                                        ? ['cal-selected bg-green-600 border-green-600'] : ['hover:bg-green-600 hover:border-green-600'];
                                },
                                eventClick: (info) => {
                                    const id = info.event.id;
                                    this.selectedDayId = id; 
                                    this.calendar.render();
                                    Livewire.dispatch('calendarEventClick', { 'id': { id } });
                                },
                            });
                            this.calendar.render();
    
                            // Dynamische Updates (z. B. via Livewire)
                            window.addEventListener('calendar:update', (e) => {
                                if (e?.detail?.events) this.setEvents(e.detail.events);
                            });
                        });
                    }, 50); // <- 50ms Verzögerung vor dem ersten waitFor()
                },
                setEvents(newEvents) {
                    if (!this.calendar) return;
                    this.events = newEvents || [];
                    this.calendar.removeAllEvents();
                    this.calendar.addEventSource(this.events);
                },
            };
        })()"
        x-init="init()"
        x-resize="width != $width ? init() : null; width = $width"
        wire:ignore
    >
        <style>
            .fc-container .fc-button-group .fc-button-primary{
                background-color: #35ae46;
                color: white;
                border-color: #35ae46;
                padding: 1px;
            }
            .fc-container .fc-button-group .fc-button-primary:hover {
                background-color: #2a7b34;
                color: white;
            }
            body .fc .fc-toolbar-title {
                font-size: 1rem;
                font-weight: 400;
                color: #111;
            }
            .fc-scroller .fc-day {
                background-color: #fff;
            }
            .fc-scroller .fc-day.fc-day-today {
                background-color: rgb(255, 243, 184);
            }
            body .fc .fc-dayGridMonth-view {
                border-radius: .5rem;
                overflow: hidden;
            }
            body  .fc .fc-daygrid-body-natural .fc-daygrid-day-events {
                margin-bottom: 3px;
            }
        </style>
        <div class="fc-container" x-ref="calendar" ></div>
    </div>
</div>


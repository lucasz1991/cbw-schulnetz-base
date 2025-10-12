@props([
    'dates' => collect(),
    'eventTitle' => 'Kurstermin',
    'dateField' => 'date',
    // time-fields werden ignoriert, können aber als Props bleiben
    'startTimeField' => 'start_time',
    'endTimeField' => 'end_time',
    'selectedDayId' => null,
    'dispatchModul' => null,
])

@php
    use Illuminate\Support\Carbon;

    // Immer all-day Events erzeugen (nur Datum, keine Zeiten)
    $events = collect($dates)->map(function ($item, $idx) use ($dateField) {
        $dateValue = is_object($item) || is_array($item) ? data_get($item, $dateField) : $item;

        // Robuste Datumsermittlung -> 'Y-m-d'
        $dateStr = $dateValue instanceof \DateTimeInterface
            ? $dateValue->format('Y-m-d')
            : Carbon::parse($dateValue)->format('Y-m-d');

        // Echte DB-ID (fallback auf Laufindex)
        $eventId = (string) (data_get($item, 'id') ?? ($idx + 1));

        return [
            'id'     => $eventId,
            'title'  => '',
            'start'  => $dateStr,   // nur Tag
            'allDay' => true,       // immer Ganztag
        ];
    })->values();
@endphp

<div x-data="{ width: 0 }" class="">
  <div
    {{ $attributes->merge(['class' => 'show-dates-calendar']) }}
    x-data="(() => {
      const eventsFromServer = @js($events);

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
          headerToolbar: { left: '', center: '', right: 'title' },
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
                    ? ['cal-selected bg-green-200 border-green-300']
                    : ['bg-blue-200 border-blue-300 hover:bg-green-200 hover:border-green-300'];
                },
                eventClick: (info) => {
                const id = info.event.id;
                this.selectedDayId = id;
                this.calendar.render();

                const target = @js($dispatchModul); 

                if (target) {
                    Livewire.dispatch('calendarEventClick', { id }, { to: target });
                }
                },
              });

              this.calendar.render();

              // Dynamische Updates
              window.addEventListener('calendar:update', (e) => {
                if (e?.detail?.events) this.setEvents(e.detail.events);
              });
            });
          }, 50);
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
        background-color: #35ae46; color: white; border-color: #35ae46; padding: 1px;
      }
      .fc-container .fc-button-group .fc-button-primary:hover {
        background-color: #2a7b34; color: white;
      }
      body .fc .fc-toolbar-title { font-size: 1rem; font-weight: 400; color: #111; }
      .fc-scroller .fc-day { background-color: #fff; }
      .fc-scroller .fc-day.fc-day-today { background-color: rgb(255, 243, 184); }
      body .fc .fc-dayGridMonth-view { border-radius: .5rem; overflow: hidden; }
      body .fc-scroller .fc-day { background-color: #fff;pointer-events:none; }
      body .fc .fc-daygrid-body-natural .fc-daygrid-day-frame{
        min-height:3rem;

      }
      body .fc .fc-daygrid-body-natural .fc-daygrid-day-top{
            z-index: 10;
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            pointer-events:none;
            padding-right:3px;
      }
      body .fc .fc-daygrid-body-natural .fc-daygrid-day-top .fc-daygrid-day-number{
        font-size: 0.8rem;
      }
      body .fc .fc-daygrid-body-natural .fc-daygrid-day-events { 
            margin:0px; 
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            z-index:5;
             pointer-events:auto;
            padding:2px;
        }

        body .fc .fc-daygrid-body-natural .fc-daygrid-day-events .fc-event, body .fc .fc-daygrid-body-natural .fc-daygrid-day-events .fc-daygrid-event-harness{
            width: 100%;
            height: 100%;
            margin:0px; 
        }
    </style>
    <div class="fc-container" x-ref="calendar"></div>
  </div>
</div>

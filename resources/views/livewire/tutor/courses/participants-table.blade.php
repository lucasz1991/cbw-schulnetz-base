<div
    x-data="{ showSelectDayCalendar: $persist(false) }"
    wire:poll.5s
    @class([
        'opacity-50 pointer-events-none cursor-wait' => $isLoadingApi,
    ])
>
    @if($course->dates->count() > 0)
        <div class="flex space-x-8">
            <div class="mt-6 w-full transition-all duration-600 ease-in-out"
                :class="{
                    ' md:w-2/3 xl:w-4/5 ': showSelectDayCalendar
                }"
            >

                
                @if($selectedDayId)
                    <x-ui.tutor.course.show-date-attendance
                        :participants="$participants"
                        :selectedDay="$selectedDay"
                        :stats="$stats"
                        :rows="$rows"
                        :sortBy="$sortBy"
                        :sortDir="$sortDir"
                        :selectPreviousDayPossible="$selectPreviousDayPossible"
                        :selectNextDayPossible="$selectNextDayPossible"
                        :plannedStart="$plannedStart" 
                        :plannedEnd="$plannedEnd"    
                    />
                    @if($isDirty)
                        <x-button
                            class="disabled:opacity-60 disabled:cursor-wait mt-2"
                            wire:click="saveChanges"
                            wire:loading.attr="disabled"
                            wire:target="saveChanges"
                        >
                            Änderungen senden
                        </x-button>
                    @endif
                @else
                    <p class="text-sm text-gray-500">Kein Datum ausgewählt.</p>
                @endif
            </div>
            <div class="hidden md:block  w-full mt-2 "
                :class="{
                    'md:w-1/3 xl:w-1/5': showSelectDayCalendar
                }"
                x-show="showSelectDayCalendar"
                x-transition:enter="transition ease-out duration-600"
            >
                <x-calendar.select-date
                    :dates="$course->dates"
                    :eventTitle="$course->title"
                    :selectedDayId="$selectedDayId"
                    dateField="date"
                    startTimeField="start_time"
                    endTimeField="end_time"
                    dispatchModul="tutor.courses.participants-table"
                />
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500">Keine Termine vorhanden.</p>
    @endif
</div>

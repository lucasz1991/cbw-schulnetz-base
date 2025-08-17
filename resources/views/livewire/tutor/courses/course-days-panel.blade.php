<div class="">
    @if($course->dates->count() > 0)
        <div class="flex space-x-8">
            <div class="mt-2 md:w-2/3 xl:w-4/5 w-full">
                @if($selectedDayId)
                    <x-ui.tutor.course.show-date
                        :selectedDay="$selectedDay"
                        :selectedDaySessionId="$selectedDaySessionId"
                        :selectPreviousDayPossible="$selectPreviousDayPossible"
                        :selectNextDayPossible="$selectNextDayPossible"
                    />
                @else
                    <p class="text-sm text-gray-500">Kein Datum ausgewählt.</p>
                @endif
            </div>
            <div class="md:w-1/3 xl:w-1/5 w-full">
                <x-calendar.select-date
                    :dates="$course->dates"
                    :eventTitle="$course->title"
                    :selectedDayId="$selectedDayId"
                    dateField="date"
                    startTimeField="start_time"
                    endTimeField="end_time"
                />
            </div>
        </div>
    @else
        <p class="text-sm text-gray-500">Keine Termine vorhanden.</p>
    @endif
</div>

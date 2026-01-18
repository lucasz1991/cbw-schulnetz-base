<?php

namespace App\Jobs\ApiUpdates;

use App\Models\CourseDay;
use App\Services\ApiUvs\CourseApiServices\CourseDayAttendanceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class SyncCourseDayAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ID des CourseDay, der synchronisiert werden soll.
     */
    public int $courseDayId;



    /**
     * Constructor.
     */
    public function __construct(CourseDay $courseDay)
    {
        $this->courseDayId = $courseDay->id;
    } 

    /**
     * Job ausführen.
     */
    public function handle(CourseDayAttendanceSyncService $service): void
    {
        $day = CourseDay::find($this->courseDayId);

        if (!$day) {
            return;
        }
        $result = $service->syncToRemote($day);

        if (!$result) {
            Log::warning("SyncCourseDayAttendanceJob: Fehler beim Synchronisieren der Attendance für CourseDay #{$day->id}.");
        }
    }
}

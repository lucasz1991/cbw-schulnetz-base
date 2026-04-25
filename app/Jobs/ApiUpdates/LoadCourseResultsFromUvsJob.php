<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Course;
use App\Services\ApiUvs\CourseApiServices\CourseResultsLoadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoadCourseResultsFromUvsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $coursePk)
    {
        app(CourseResultsLoadService::class)->markQueuedByCourseId($this->coursePk);
    }

    public function uniqueId(): string
    {
        return 'load-course-results-from-uvs:' . (string) $this->coursePk;
    }

    public function handle(CourseResultsLoadService $service): void
    {
        $course = Course::find($this->coursePk);

        if (! $course) {
            Log::warning("LoadCourseResultsFromUvsJob: Course {$this->coursePk} nicht gefunden.");
            return;
        }

        $ok = $service->handle($course);

        if (! $ok) {
            Log::warning('LoadCourseResultsFromUvsJob: Hard load from UVS returned false.', [
                'course_id' => $course->id,
                'klassen_id' => $course->klassen_id,
                'termin_id' => $course->termin_id,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        $course = Course::find($this->coursePk);

        if ($course) {
            app(CourseResultsLoadService::class)->markFailed($course, $e->getMessage());
        }

        Log::error('LoadCourseResultsFromUvsJob failed.', [
            'course_id' => $this->coursePk,
            'error' => $e->getMessage(),
        ]);
    }
}

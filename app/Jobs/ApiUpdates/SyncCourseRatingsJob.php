<?php

namespace App\Jobs\ApiUpdates;

use App\Models\Course;
use App\Models\CourseRating;
use App\Services\ApiUvs\CourseApiServices\CourseRatingsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCourseRatingsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 180];

    public function __construct(
        public int $courseRatingId,
    ) {
    }

    public function uniqueId(): string
    {
        $rating = CourseRating::find($this->courseRatingId);
        $courseId = $rating?->course_id ?? 'unknown-course';

        return 'sync-course-ratings:' . $courseId;
    }

    public function handle(CourseRatingsSyncService $syncService): void
    {
        $debugLogs = (bool) config('api_sync.debug_logs', false);

        $rating = CourseRating::find($this->courseRatingId);
        if (! $rating) {
            if ($debugLogs) {
                Log::warning('SyncCourseRatingsJob: CourseRating nicht gefunden.', [
                    'course_rating_id' => $this->courseRatingId,
                ]);
            }
            return;
        }

        $course = Course::find($rating->course_id);
        if (! $course) {
            if ($debugLogs) {
                Log::warning('SyncCourseRatingsJob: Course nicht gefunden.', [
                    'course_rating_id' => $this->courseRatingId,
                    'course_id'        => $rating->course_id,
                ]);
            }
            return;
        }

        $ok = $syncService->syncToRemote($course);

        if ($debugLogs) {
            Log::info('SyncCourseRatingsJob finished.', [
                'course_rating_id' => $this->courseRatingId,
                'course_id'        => $course->id,
                'synced'           => $ok,
            ]);
        }
    }
}

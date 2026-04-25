<?php

namespace App\Services\ApiUvs\CourseApiServices;

use App\Jobs\ApiUpdates\LoadCourseResultsFromUvsJob;
use App\Models\Course;

class CourseResultsLoadService
{
    public const SETTING_STATUS = 'results_load_status';
    public const SETTING_QUEUED_AT = 'results_load_queued_at';
    public const SETTING_STARTED_AT = 'results_load_started_at';
    public const SETTING_FINISHED_AT = 'results_load_finished_at';
    public const SETTING_ERROR = 'results_load_error';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    public function queue(Course $course): void
    {
        LoadCourseResultsFromUvsJob::dispatch($course->id);
    }

    public function markQueuedByCourseId(int $coursePk): void
    {
        $course = Course::find($coursePk);

        if (! $course) {
            return;
        }

        $this->markQueued($course);
    }

    public function markQueued(Course $course): void
    {
        $course->setSetting(self::SETTING_STATUS, self::STATUS_QUEUED);
        $course->setSetting(self::SETTING_QUEUED_AT, now()->toDateTimeString());
        $course->setSetting(self::SETTING_STARTED_AT, null);
        $course->setSetting(self::SETTING_FINISHED_AT, null);
        $course->setSetting(self::SETTING_ERROR, null);
        $course->saveQuietly();
    }

    public function markRunning(Course $course): void
    {
        $course->setSetting(self::SETTING_STATUS, self::STATUS_RUNNING);
        $course->setSetting(self::SETTING_STARTED_AT, now()->toDateTimeString());
        $course->setSetting(self::SETTING_FINISHED_AT, null);
        $course->setSetting(self::SETTING_ERROR, null);
        $course->saveQuietly();
    }

    public function markDone(Course $course): void
    {
        $course->setSetting(self::SETTING_STATUS, self::STATUS_DONE);
        $course->setSetting(self::SETTING_FINISHED_AT, now()->toDateTimeString());
        $course->setSetting(self::SETTING_ERROR, null);
        $course->saveQuietly();
    }

    public function markFailed(Course $course, ?string $error = null): void
    {
        $course->setSetting(self::SETTING_STATUS, self::STATUS_FAILED);
        $course->setSetting(self::SETTING_FINISHED_AT, now()->toDateTimeString());
        $course->setSetting(self::SETTING_ERROR, $error);
        $course->saveQuietly();
    }

    public function handle(Course $course): bool
    {
        $this->markRunning($course);

        $ok = $course->loadResultsFromUvs();

        if ($ok) {
            $this->markDone($course);

            return true;
        }

        $this->markFailed($course);

        return false;
    }
}

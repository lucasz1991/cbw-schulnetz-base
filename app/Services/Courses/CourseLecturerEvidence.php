<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\CourseDay;
use App\Models\File;
use Illuminate\Support\Facades\Auth;

class CourseLecturerEvidence
{
    private static function freshCourse(Course $course): Course
    {
        return $course->exists ? Course::withoutEvents(fn () => Course::query()->findOrFail($course->id)) : $course;
    }

    public static function guardView(Course $course): void
    {
        $course = self::freshCourse($course);
        if (CourseLecturerAccess::hasPlan($course)) {
            abort_unless(CourseLecturerAccess::canView(Auth::user(), $course), 403);
        }
    }

    public static function guardDay(CourseDay $day): void
    {
        $course = $day->course()->firstOrFail();
        if (! CourseLecturerAccess::hasPlan($course) || CourseLecturerAccess::isSystemSync()) {
            return;
        }
        abort_unless(CourseLecturerAccess::canWriteDay(Auth::user(), $course, $day), 403,
            'Dieser Unterrichtstag gehört nicht zu Ihrer aktuellen Zuständigkeit.');
    }

    public static function savingDay(CourseDay $day): void
    {
        $protected = ['notes', 'note_status', 'attendance_data', 'day_sessions', 'topic', 'date', 'start_time', 'end_time'];
        if (! $day->isDirty($protected)) {
            return;
        }
        $course = $day->course()->first();
        if (! $course || ! CourseLecturerAccess::hasPlan($course) || CourseLecturerAccess::isSystemSync()) {
            return;
        }
        self::guardDay($day);
        if ($day->isDirty('attendance_data')) {
            $data = $day->attendance_data ?? [];
            $old = $day->getOriginal('attendance_data') ?? [];
            $assignment = CourseLecturerAccess::currentAssignment($course, $day->date);
            foreach (($data['participants'] ?? []) as $id => $row) {
                if ($row === ($old['participants'][$id] ?? null)) {
                    continue;
                }
                abort_unless($course->participants()->where('persons.id', $id)->exists(), 403);
                $data['participants'][$id] = array_merge($row, [
                    'updated_by' => Auth::id(), 'lecturer_assignment_id' => $assignment['assignment_id'],
                    'lecturer_plan_revision' => (int) $course->lecturer_plan_revision,
                ]);
            }
            $day->attendance_data = $data;
        }
        if ($day->isDirty(['notes', 'note_status'])) {
            $original = (string) $day->getOriginal('notes');
            $finalizing = (int) $day->note_status === CourseDay::NOTE_STATUS_COMPLETED && ! $day->isDirty('notes')
                && $day->tutorSignatures()->where('user_id', Auth::id())->where('document_revision', (int) $day->notes_revision)->exists();
            abort_if((int) $day->getOriginal('note_status') === CourseDay::NOTE_STATUS_COMPLETED
                || ($day->tutorSignatures()->exists() && ! $finalizing), 409, 'Unterschriebene Dokumentationen bleiben unverändert.');
            abort_if($day->notes_author_user_id && (int) $day->notes_author_user_id !== (int) Auth::id(), 403,
                'Fremde Dokumentationen dürfen nicht bearbeitet werden.');
            abort_if(! $day->notes_author_user_id && trim($original) !== '', 409,
                'Die vorhandene Dokumentation benötigt zuerst eine bestätigte Autorenzuordnung durch die Verwaltung.');
            $assignment = CourseLecturerAccess::currentAssignment($course, $day->date);
            $day->notes_author_user_id = Auth::id();
            $day->notes_assignment_id = $assignment['assignment_id'] ?? null;
            if ($day->isDirty('notes')) {
                $day->notes_revision = (int) $day->getOriginal('notes_revision') + 1;
            }
        }
    }

    public static function guardResults(Course $course): void
    {
        if (CourseLecturerAccess::isSystemSync()) {
            return;
        }
        $course = self::freshCourse($course);
        if (CourseLecturerAccess::hasPlan($course) && ! CourseLecturerAccess::isSystemSync()) {
            abort_unless(CourseLecturerAccess::canManageResults(Auth::user(), $course), 403,
                'Abschlussergebnisse dürfen nur vom zuständigen Abschlussdozenten bearbeitet werden.');
        }
    }

    public static function guardMaterials(Course $course): void
    {
        $course = self::freshCourse($course);
        if (CourseLecturerAccess::hasPlan($course)) {
            abort_unless(CourseLecturerAccess::canManageCourse(Auth::user(), $course), 403);
        }
    }

    public static function guardFileRead(File $file): void
    {
        if (CourseLecturerAccess::isSystemSync()) {
            return;
        }
        if ($file->exists) {
            $file = File::withoutEvents(fn () => File::query()->findOrFail($file->id));
        }
        $course = self::fileCourse($file);
        if (! $course instanceof Course || ! CourseLecturerAccess::hasPlan($course)) {
            return;
        }
        if ($file->type === 'invoice') {
            abort_unless(Auth::user() && ((int) $file->user_id === (int) Auth::id() || Auth::user()->can('invoices.view')), 403);
        } else {
            $participant = Auth::user() && $course->participants()->whereIn('persons.id', Auth::user()->persons()->pluck('persons.id'))->exists();
            abort_unless($participant || CourseLecturerAccess::canView(Auth::user(), $course), 403);
        }
    }

    public static function guardFileDelete(File $file): void
    {
        if ($file->exists) {
            $file = File::withoutEvents(fn () => File::query()->findOrFail($file->id));
        }
        $course = self::fileCourse($file);
        if (! $course instanceof Course || ! CourseLecturerAccess::hasPlan($course)) {
            return;
        }
        abort_if(str_starts_with((string) $file->type, 'sign_') || $file->type === 'invoice', 409,
            'Unterschriften und Rechnungsstände bleiben als Nachweis erhalten. Für Rechnungen kann eine neue Fassung hochgeladen werden.');
        self::guardMaterials($course);
    }

    private static function fileCourse(File $file): ?Course
    {
        $subject = $file->fileable()->first();
        $course = $subject instanceof Course ? $subject : ($subject instanceof CourseDay ? $subject->course()->first() : null);
        if ($subject instanceof \App\Models\FilePool) {
            $course = $subject->filepoolable()->first();
        }

        return $course instanceof Course ? self::freshCourse($course) : null;
    }
}

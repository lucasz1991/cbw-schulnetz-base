<?php

namespace Tests\Feature;

use App\Jobs\ReportBook\CheckReportBooks;
use App\Models\AdminTask;
use App\Models\ReportBook;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportBookTaskAssignmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'reportbook_task_testing');
        config()->set('database.connections.reportbook_task_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('reportbook_task_testing');
        DB::reconnect('reportbook_task_testing');

        $this->createSchema();
    }

    public function test_reportbook_check_preserves_an_active_assignment(): void
    {
        [$reportBookId, $taskId] = $this->createSubmittedReportBookTask(
            AdminTask::STATUS_IN_PROGRESS,
            2
        );

        (new CheckReportBooks([$reportBookId]))->handle();

        $task = AdminTask::query()->findOrFail($taskId);

        $this->assertSame(AdminTask::STATUS_IN_PROGRESS, (int) $task->status);
        $this->assertSame(2, (int) $task->assigned_to);
        $this->assertNull($task->completed_at);
    }

    public function test_reportbook_check_still_reactivates_a_completed_task(): void
    {
        [$reportBookId, $taskId] = $this->createSubmittedReportBookTask(
            AdminTask::STATUS_COMPLETED,
            2
        );

        (new CheckReportBooks([$reportBookId]))->handle();

        $task = AdminTask::query()->findOrFail($taskId);

        $this->assertSame(AdminTask::STATUS_OPEN, (int) $task->status);
        $this->assertNull($task->assigned_to);
        $this->assertNull($task->completed_at);
    }

    public function test_base_model_uses_the_same_bounded_takeover_semantics(): void
    {
        [, $taskId] = $this->createSubmittedReportBookTask(
            AdminTask::STATUS_IN_PROGRESS,
            2
        );
        $task = AdminTask::query()->findOrFail($taskId);

        $this->assertTrue($task->takeOverBy(3));
        $this->assertSame(3, (int) $task->fresh()->assigned_to);

        $task->forceFill([
            'status' => AdminTask::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        $this->assertFalse($task->takeOverBy(4));
        $this->assertSame(3, (int) $task->fresh()->assigned_to);
    }

    public function test_repeated_checks_keep_one_task_per_reportbook(): void
    {
        [$reportBookId, $taskId] = $this->createSubmittedReportBookTask(
            AdminTask::STATUS_OPEN,
            null
        );
        AdminTask::query()->whereKey($taskId)->delete();

        (new CheckReportBooks([$reportBookId]))->handle();
        (new CheckReportBooks([$reportBookId]))->handle();

        $tasks = AdminTask::query()
            ->where('task_type', AdminTask::TYPE_REPORTBOOK_REVIEW)
            ->where('context_type', ReportBook::class)
            ->where('context_id', $reportBookId)
            ->get();

        $this->assertCount(1, $tasks);
        $this->assertSame(AdminTask::STATUS_OPEN, (int) $tasks->first()->status);
        $this->assertNull($tasks->first()->assigned_to);
    }

    public function test_legacy_fallback_matches_the_whole_id_and_restores_its_context(): void
    {
        [$reportBookId, $taskId] = $this->createSubmittedReportBookTask(
            AdminTask::STATUS_OPEN,
            null
        );
        AdminTask::query()->whereKey($taskId)->delete();

        $nearMatchId = DB::table('admin_tasks')->insertGetId([
            'created_by' => 1,
            'context_type' => null,
            'context_id' => null,
            'task_type' => AdminTask::TYPE_REPORTBOOK_REVIEW,
            'description' => "Baustein Berichtsheft {$reportBookId}9 vollständig eingereicht",
            'status' => AdminTask::STATUS_OPEN,
            'priority' => AdminTask::PRIORITY_NORMAL,
            'assigned_to' => null,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $legacyId = DB::table('admin_tasks')->insertGetId([
            'created_by' => 1,
            'context_type' => null,
            'context_id' => null,
            'task_type' => AdminTask::TYPE_REPORTBOOK_REVIEW,
            'description' => "ReportBook {$reportBookId} vollständig eingereicht",
            'status' => AdminTask::STATUS_IN_PROGRESS,
            'priority' => AdminTask::PRIORITY_NORMAL,
            'assigned_to' => 2,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new CheckReportBooks([$reportBookId]))->handle();

        $legacy = AdminTask::query()->findOrFail($legacyId);
        $nearMatch = AdminTask::query()->findOrFail($nearMatchId);

        $this->assertSame(ReportBook::class, $legacy->context_type);
        $this->assertSame($reportBookId, (int) $legacy->context_id);
        $this->assertSame(2, (int) $legacy->assigned_to);
        $this->assertNull($nearMatch->context_type);
        $this->assertNull($nearMatch->context_id);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function createSubmittedReportBookTask(int $status, ?int $assignedTo): array
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Teilnehmer', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Bearbeiter', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Vertretung', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Weitere Vertretung', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Testbaustein',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $courseDayId = DB::table('course_days')->insertGetId([
            'course_id' => $courseId,
            'date' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportBookId = DB::table('report_books')->insertGetId([
            'user_id' => 1,
            'course_id' => $courseId,
            'title' => 'Mein Berichtsheft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('report_book_entries')->insert([
            'report_book_id' => $reportBookId,
            'course_day_id' => $courseDayId,
            'entry_date' => '2026-08-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $taskId = DB::table('admin_tasks')->insertGetId([
            'created_by' => 1,
            'context_type' => ReportBook::class,
            'context_id' => $reportBookId,
            'task_type' => AdminTask::TYPE_REPORTBOOK_REVIEW,
            'description' => "Baustein Berichtsheft {$reportBookId} vollständig eingereicht",
            'status' => $status,
            'priority' => AdminTask::PRIORITY_NORMAL,
            'assigned_to' => $assignedTo,
            'completed_at' => $status === AdminTask::STATUS_COMPLETED ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$reportBookId, $taskId];
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('course_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->date('date');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('report_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('title')->nullable();
            $table->text('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('report_book_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_book_id');
            $table->unsignedBigInteger('course_day_id')->nullable();
            $table->date('entry_date')->nullable();
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by');
            $table->nullableMorphs('context');
            $table->string('task_type');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(AdminTask::STATUS_OPEN);
            $table->unsignedTinyInteger('priority')->default(AdminTask::PRIORITY_NORMAL);
            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
}

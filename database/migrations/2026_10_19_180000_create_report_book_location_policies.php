<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_book_institute_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('institut_id')->unique();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
        Schema::create('report_book_policy_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('policy_id')->index();
            $table->unsignedInteger('revision');
            $table->boolean('required')->default(true);
            $table->boolean('stamp_enabled')->default(false);
            $table->string('stamp_path')->nullable();
            $table->string('stamp_sha256', 64)->nullable();
            $table->string('stamp_mime', 32)->nullable();
            $table->dateTime('effective_at')->index(); // UTC, independent of the application timezone.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at');
            $table->unique(['policy_id', 'revision'], 'rb_policy_revision_unique');
        });
        Schema::create('report_book_approval_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('revision_id')->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->json('snapshot')->nullable();
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('report_book_policy_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('revision_id')->nullable()->index();
            $table->unsignedBigInteger('run_id')->nullable()->index();
            $table->string('event_key', 191)->unique();
            $table->string('event', 48)->index();
            $table->unsignedBigInteger('book_id')->nullable()->index();
            $table->unsignedBigInteger('entry_id')->nullable()->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload');
            $table->dateTime('created_at');
            // Deliberately no cascading foreign keys: historical evidence outlives its source.
        });
        Schema::table('report_book_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('automated_approval_audit_id')->nullable()->index('rb_entry_approval_audit_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('report_book_policy_revisions')->exists() || DB::table('report_book_policy_audit')->exists()) {
            throw new RuntimeException('Freigabehistorie vorhanden: Diese Migration darf nicht verlustbehaftet zurückgerollt werden.');
        }
        Schema::table('report_book_entries', fn (Blueprint $table) => $table->dropColumn('automated_approval_audit_id'));
        Schema::dropIfExists('report_book_policy_audit');
        Schema::dropIfExists('report_book_approval_runs');
        Schema::dropIfExists('report_book_policy_revisions');
        Schema::dropIfExists('report_book_institute_policies');
    }
};

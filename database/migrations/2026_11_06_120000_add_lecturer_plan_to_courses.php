<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lecturer_plan', 'lecturer_plan_revision', 'lecturer_plan_synced_at', 'lecturer_closing_assignment_id', 'lecturer_closed_at'] as $field) {
            if (Schema::hasColumn('courses', $field)) {
                continue;
            }
            Schema::table('courses', function (Blueprint $t) use ($field) {
                if ($field === 'lecturer_plan') {
                    $t->json($field)->nullable();
                } elseif ($field === 'lecturer_plan_revision') {
                    $t->unsignedInteger($field)->default(0);
                } elseif ($field === 'lecturer_closing_assignment_id') {
                    $t->string($field, 64)->nullable();
                } else {
                    $t->dateTime($field)->nullable();
                }
            });
        }
        if (Schema::hasTable('course_ratings')) {
            foreach (['lecturer_assignment_id', 'lecturer_plan_revision', 'lecturer_person_id', 'lecturer_contract_id', 'lecturer_staff_id'] as $field) {
                if (Schema::hasColumn('course_ratings', $field)) {
                    continue;
                }
                Schema::table('course_ratings', function (Blueprint $t) use ($field) {
                    if ($field === 'lecturer_person_id') {
                        $t->unsignedBigInteger($field)->nullable();
                    } elseif ($field === 'lecturer_plan_revision') {
                        $t->unsignedInteger($field)->nullable();
                    } else {
                        $t->string($field, 64)->nullable();
                    }
                });
            }
        }
        if (Schema::hasTable('course_results')) {
            foreach (['lecturer_assignment_id', 'lecturer_plan_revision'] as $field) {
                if (Schema::hasColumn('course_results', $field)) {
                    continue;
                }
                Schema::table('course_results', function (Blueprint $t) use ($field) {
                    if ($field === 'lecturer_plan_revision') {
                        $t->unsignedInteger($field)->nullable();
                    } else {
                        $t->string($field, 64)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Shared Base/Admin schema: preserve assignment evidence on rollback.
    }
};

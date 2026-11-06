<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'course_days' => ['notes_author_user_id'=>'id','notes_assignment_id'=>'string','notes_revision'=>'integer'],
            'files' => ['lecturer_person_id'=>'id','lecturer_contract_id'=>'string','lecturer_assignment_id'=>'string',
                'lecturer_plan_revision'=>'integer','document_revision'=>'integer','supersedes_file_id'=>'id'],
        ];
        foreach ($columns as $table=>$fields) {
            if (!Schema::hasTable($table)) continue;
            foreach ($fields as $name=>$type) {
                if (Schema::hasColumn($table,$name)) continue;
                Schema::table($table,function(Blueprint $t)use($name,$type){
                    if($type==='id')$t->unsignedBigInteger($name)->nullable();
                    elseif($type==='integer')$t->unsignedInteger($name)->nullable();
                    else $t->string($name,80)->nullable();
                });
            }
        }
    }
    // Evidence must survive a code rollback; do not silently discard author/history bindings.
    public function down(): void {}
};

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeExamRulesCombineSubject extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('exam_rules', function (Blueprint $table) {
            $table->dropForeign('exam_rules_combine_subject_id_foreign');
            $table->dropColumn('combine_subject_id');
        });
        Schema::table('exam_rules', function (Blueprint $table) {
            $table->text('combine_subject_id')->nullable()->after('grade_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('exam_rules', function (Blueprint $table) {
            $table->dropColumn('combine_subject_id');
        });
        
        Schema::table('exam_rules', function (Blueprint $table) {
            $table->unsignedInteger('combine_subject_id')->after('grade_id')->nullable();
            $table->foreign('combine_subject_id')->references('id')->on('subjects');
        });
    }
}

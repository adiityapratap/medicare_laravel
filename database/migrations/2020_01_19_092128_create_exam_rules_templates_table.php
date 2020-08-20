<?php

use App\Http\Helpers\AppHelper;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExamRulesTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exam_rules_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedInteger('grade_id');
            $table->enum('passing_rule', array_keys(AppHelper::PASSING_RULES));
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('grade_id')->references('id')->on('grades');
        });
        Schema::create('default_exam_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('template');
            $table->unsignedInteger('subject_id');
            $table->text('marks_distribution');
            $table->text('combine_subject');
            $table->integer('total_exam_marks');
            $table->integer('over_all_pass');
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('template')->references('id')->on('exam_rules_templates')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects');
        });

        Schema::create('exam_rule_temp_classes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('class_id');
            $table->unsignedBigInteger('template');
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('class_id')->references('id')->on('i_classes');
            $table->foreign('template')->references('id')->on('exam_rules_templates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_rule_temp_classes');
        Schema::dropIfExists('default_exam_rules');
        Schema::dropIfExists('exam_rules_templates');
        
    }
}

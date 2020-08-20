<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExamTimeTablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exam_time_tables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('i_class_id');
            $table->unsignedInteger('exam_id');
            $table->unsignedInteger('subject_id');
            $table->unsignedInteger('section_id');
            $table->timestamp('from');
            $table->timestamp('to')->default(\Carbon\Carbon::now());
            $table->timestamps();

            $table->foreign('i_class_id')->references('id')->on('i_classes');
            $table->foreign('exam_id')->references('id')->on('exams');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->foreign('section_id')->references('id')->on('sections');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exam_time_tables');
    }
}

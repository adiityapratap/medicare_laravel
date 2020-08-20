<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTimeTablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('time_tables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('i_class_id');
            $table->unsignedInteger('section_id');
            $table->unsignedInteger('subject_id');
            $table->timestamp('from');
            $table->timestamp('to')->default(\Carbon\Carbon::now());
            $table->tinyInteger('monthly_repeat')->default(0)->comment('0 = No, 1 = Yes');
            $table->tinyInteger('full_month')->default(0)->comment('0 = No, 1 = Yes');
            $table->tinyInteger('days_of_month')->default(9)->comment('0 = No');
            $table->timestamps();

            $table->foreign('i_class_id')->references('id')->on('i_classes');
            $table->foreign('section_id')->references('id')->on('sections');
            $table->foreign('subject_id')->references('id')->on('subjects');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('time_tables');
    }
}

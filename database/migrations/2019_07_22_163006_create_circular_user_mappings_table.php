<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCircularUserMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('circular_user_mappings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('circular_id');
            $table->unsignedInteger('section_id')->nullable();
            $table->unsignedInteger('student_id')->nullable();
            $table->unsignedInteger('staff_id')->nullable();
            $table->unsignedInteger('all')->nullable();
            $table->boolean('is_read')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('circular_id')->references('id')->on('circular_notifications');
            $table->foreign('section_id')->references('id')->on('sections');
            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('staff_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('circular_user_mappings');
    }
}

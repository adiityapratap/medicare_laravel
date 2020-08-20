<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessageUserMappings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('message_user_mappings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('message_id');
            $table->unsignedInteger('section_id')->nullable();;
            $table->unsignedInteger('student_id')->nullable();;
            $table->unsignedInteger('staff_id')->nullable();;
            $table->unsignedInteger('all')->nullable();;
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('message_id')->references('id')->on('message_notifications');
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
        Schema::dropIfExists('message_user_mappings');
    }
}

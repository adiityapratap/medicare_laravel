<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLmsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Lessons
        Schema::create('chapters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 500);
            $table->text('description');
            // $table->integer('user_id')->unsigned();
            $table->integer('class_id')->unsigned();
            $table->foreign('class_id')->references('id')->on('i_classes')->onDelete('cascade');
            $table->integer('subject_id')->unsigned();
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->enum('status', ['Active', 'Pending'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();
        });

        // Lesson topics
        Schema::create('topics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 500);
            $table->text('description');
            // $table->integer('user_id')->unsigned();
            $table->bigInteger('chapter_id')->unsigned();
            $table->foreign('chapter_id')->references('id')->on('chapters')->onDelete('cascade');
            $table->enum('status', ['Active', 'Pending'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('topics');
    }
}

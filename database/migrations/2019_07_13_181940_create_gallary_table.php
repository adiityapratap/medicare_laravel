<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGallaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gallary', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->unsignedInteger('class_id')->nullable();  
            $table->timestamps();
        });
        Schema::table('gallary', function(Blueprint $table) {
            $table->foreign('class_id')->references('id')->on('sections');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gallary', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
        });
        Schema::dropIfExists('gallary');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVideoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('video', function (Blueprint $table) {
            $table->increments('id');
            $table->string('videourl');
            $table->unsignedInteger('gal_id')->nullable();
            $table->timestamps();
        });
        Schema::table('video', function(Blueprint $table) {
            $table->foreign('gal_id')->references('id')->on('gallary');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('video', function (Blueprint $table) {
            $table->dropForeign(['gal_id']);
        });
        Schema::dropIfExists('video');
    }
}

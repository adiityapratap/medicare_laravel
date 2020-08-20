<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableFeeSetupClassMap extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_setup_class_map', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fee_item');
            $table->unsignedInteger('class_id');
            
            $table->foreign('class_id')->references('id')->on('i_classes');
            $table->foreign('fee_item')->references('id')->on('fee_setup')->onDelete('cascade');;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fee_setup_class_map');
    }
}

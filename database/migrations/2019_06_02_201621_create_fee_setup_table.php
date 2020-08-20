<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeeSetupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_setup', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type',15);
            $table->string('title',100);
            $table->decimal('fee', 11, 2);
            $table->decimal('Latefee', 5, 2)->nullable();
            $table->string('description',250)->nullable();
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
        Schema::dropIfExists('fee_setup');
    }
}

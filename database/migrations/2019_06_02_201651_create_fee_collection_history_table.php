<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeeCollectionHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_collection_history', function (Blueprint $table) {
            $table->increments('id');
            $table->string('billNo',15);
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('class_id');
            $table->unsignedInteger('fee_item');
            $table->unsignedInteger('payment');
            $table->string('type',15);
            $table->decimal('discount', 11, 2);
            $table->decimal('latefee', 5, 2);
            $table->decimal('payableAmount', 11, 2);
            $table->decimal('paidAmount', 11, 2);
            $table->decimal('dueAmount', 11, 2);
            $table->dateTime('payDate');
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('student_id')->references('id')->on('students');
            $table->foreign('class_id')->references('id')->on('i_classes');
            $table->foreign('fee_item')->references('id')->on('fee_setup');
            $table->foreign('payment')->references('id')->on('fee_collection_meta');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fee_collection_history');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFeeInstallmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('feeitem')->unsigned();
            $table->dateTime('due_date');
            $table->decimal('latefee',5, 2)->nullable();
            $table->enum('lftype', ['fixed', 'daily'])->default('fixed')->comment('Late fee type');
            $table->enum('inst_type', ['fixed', 'perc'])->default('fixed')->comment('Installment type');
            $table->decimal('inst_fee', 11, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
            $table->softUserstamps();

            $table->foreign('feeitem')->references('id')->on('fee_setup')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fee_installments');
    }
}

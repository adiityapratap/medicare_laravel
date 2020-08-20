<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePreAdmissionFormsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pre_admission_forms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('field_title', 500);
            $table->string('field_name', 500);
            $table->enum('initial_fields', [0, 1])->default(0);
            $table->enum('mandatory', [0, 1])->default(1);
            $table->enum('status', [0, 1])->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pre_admission_forms');
    }
}

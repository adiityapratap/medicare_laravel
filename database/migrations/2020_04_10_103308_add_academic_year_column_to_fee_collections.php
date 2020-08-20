<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAcademicYearColumnToFeeCollections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fee_collection', function (Blueprint $table) {
            $table->unsignedInteger('academic_year')->default(1);

            $table->foreign('academic_year')->references('id')->on('academic_years')->onDelete('cascade');
        });
        Schema::table('fee_collection_history', function (Blueprint $table) {
            $table->unsignedInteger('academic_year')->default(1);

            $table->foreign('academic_year')->references('id')->on('academic_years')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fee_collection', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });
        Schema::table('fee_collection_history', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });
    }
}

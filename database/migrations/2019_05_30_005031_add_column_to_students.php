<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Http\Helpers\AppHelper;
use Illuminate\Support\Arr;

class AddColumnToStudents extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('pob')->after('dob')->nullable();
            $table->string('caste')->after('religion')->nullable(); 
            $table->string('castecategory')->after('caste')->nullable(); 
            $table->string('nationalid')->after('nationality')->nullable(); 
            $table->string('monther_tongue')->nullable();
            $table->enum('need_transport', array_keys(AppHelper::NEED_TRANSPORT))->nullable();
            $table->integer('transport_zone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('pob');
            $table->dropColumn('caste');
            $table->dropColumn('castecategory');
            $table->dropColumn('nationalid');
            $table->dropColumn('monther_tongue');
            $table->dropColumn('need_transport');
            $table->dropColumn('transport_zone');
        });
    }
}

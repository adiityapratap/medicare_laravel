<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMessageIdToVoiceLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('voice_logs', function (Blueprint $table) {
            $table->unsignedInteger('message_id');

            $table->foreign('message_id')->references('id')->on('message_notifications')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('voice_logs', function (Blueprint $table) {
            $table->dropColumn('message_id');
        });
    }
}

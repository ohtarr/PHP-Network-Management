<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            //$table->dropColumn('name');
            //$table->dropColumn('model');
            $table->dropColumn('serial');
            $table->dropColumn('version');
            $table->dropColumn('run');
            $table->dropColumn('inventory');
            $table->dropColumn('interfaces');
            $table->dropColumn('mac');
            $table->dropColumn('arp');
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};

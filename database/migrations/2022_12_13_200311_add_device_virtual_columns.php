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
            $table->string('name', 255)->virtualAs('JSON_UNQUOTE(data->"$.name")')->nullable()->after('data')->index();
            $table->string('model', 255)->virtualAs('JSON_UNQUOTE(data->"$.model")')->nullable()->after('name')->index();
            $table->string('serial', 255)->virtualAs('JSON_UNQUOTE(data->"$.serial")')->nullable()->after('model')->index();
            $table->longText('version')->virtualAs('JSON_UNQUOTE(data->"$.version")')->nullable()->after('serial');
            $table->longText('run')->virtualAs('JSON_UNQUOTE(data->"$.run")')->nullable()->after('version');
            $table->longText('inventory')->virtualAs('JSON_UNQUOTE(data->"$.inventory")')->nullable()->after('run');
            $table->longText('interfaces')->virtualAs('JSON_UNQUOTE(data->"$.interfaces")')->nullable()->after('inventory');
            $table->longText('mac')->virtualAs('JSON_UNQUOTE(data->"$.mac")')->nullable()->after('interfaces');
            $table->longText('arp')->virtualAs('JSON_UNQUOTE(data->"$.arp")')->nullable()->after('mac');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->dropColumn('model');
            $table->dropColumn('serial]');
            $table->dropColumn('version');
            $table->dropColumn('run');
            $table->dropColumn('inventory');
            $table->dropColumn('interfaces');
            $table->dropColumn('mac');
            $table->dropColumn('arp');
        });
    }
};

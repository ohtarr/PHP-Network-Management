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
        Schema::create('devices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->ipAddress('ip');
            //$table->string('name')->nullable()->virtualAs('data->>"$.name"')->index('name');
            $table->integer('credential_id')->nullable();
            //$table->string('vendor')->nullable()->virtualAs('data->>"$.vendor"')->index('vendor');
            //$table->string('model')->nullable()->virtualAs('data->>"$.model"')->index('model');
            //$table->string('serial')->nullable()->virtualAs('data->>"$.serial"')->index('serial');
            $table->json('data')->nullable();
            $table->unique('ip');
            $table->index('type');
            $table->index('credential_id');
            $table->softDeletes();
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
        Schema::dropIfExists('devices');
    }
};

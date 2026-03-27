<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn(['controller', 'method', 'status']);
            $table->string('username')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->string('controller')->nullable();
            $table->string('method')->nullable();
            $table->boolean('status')->default(true);
        });
    }
};

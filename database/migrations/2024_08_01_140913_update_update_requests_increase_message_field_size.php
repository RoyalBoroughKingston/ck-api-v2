<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('update_requests', function (Blueprint $table) {
            $table->string('rejection_message', 1000)->after('data')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('update_requests', function (Blueprint $table) {
            $table->string('rejection_message', 1000)->after('data')->nullable()->change();
        });
    }
};

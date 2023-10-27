<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('report_types')->delete();

        $now = Date::now();

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Users Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Services Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Organisations Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Locations Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Referrals Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Feedback Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Audit Logs Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Search Histories Export',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('report_types')->delete();

        $now = Date::now();

        DB::table('report_types')->insert([
            'id' => uuid(),
            'name' => 'Commissioners Report',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};

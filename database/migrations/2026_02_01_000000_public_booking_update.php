<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies database/sql/vector7_update_001_public_booking.sql to databases created
 * before public booking existed. Fresh installs already have these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_requests')) {
            return;
        }

        $sql = file_get_contents(database_path('sql/vector7_update_001_public_booking.sql'));
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        // Irreversible by design.
    }
};

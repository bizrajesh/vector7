<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the whole Vector7 schema from database/sql/vector7_schema.sql so the
 * migration and the standalone SQL script can never drift apart.
 * If the SQL was already imported through phpMyAdmin, this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            return;
        }

        $sql = file_get_contents(database_path('sql/vector7_schema.sql'));
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        // Irreversible by design: dropping business data must be a deliberate manual action.
    }
};

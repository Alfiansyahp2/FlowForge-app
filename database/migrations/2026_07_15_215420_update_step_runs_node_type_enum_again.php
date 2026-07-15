<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extend node_type to support 'math' node.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop existing check constraint, then re-add with all 6 values
            DB::statement('ALTER TABLE step_runs DROP CONSTRAINT IF EXISTS step_runs_node_type_check');
            DB::statement("ALTER TABLE step_runs ADD CONSTRAINT step_runs_node_type_check CHECK (node_type IN ('http','delay','condition','script','notification','math'))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE step_runs MODIFY COLUMN node_type ENUM('http','delay','condition','script','notification','math') NOT NULL");
        }
        // SQLite: no enum enforcement — already works as string
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE step_runs DROP CONSTRAINT IF EXISTS step_runs_node_type_check');
            DB::statement("ALTER TABLE step_runs ADD CONSTRAINT step_runs_node_type_check CHECK (node_type IN ('http','delay','condition','script','notification'))");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE step_runs MODIFY COLUMN node_type ENUM('http','delay','condition','script','notification') NOT NULL");
        }
    }
};

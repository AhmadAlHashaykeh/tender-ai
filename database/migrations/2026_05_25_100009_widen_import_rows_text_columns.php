<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE import_rows
                MODIFY raw_inn TEXT NULL,
                MODIFY raw_product_name TEXT NULL,
                MODIFY raw_winner TEXT NULL,
                MODIFY raw_company_name TEXT NULL,
                MODIFY error_message TEXT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE import_rows
                ALTER COLUMN raw_inn TYPE TEXT,
                ALTER COLUMN raw_product_name TYPE TEXT,
                ALTER COLUMN raw_winner TYPE TEXT,
                ALTER COLUMN raw_company_name TYPE TEXT,
                ALTER COLUMN error_message TYPE TEXT');

            return;
        }

        // SQLite and others: no VARCHAR length enforcement; schema unchanged is safe for tests.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE import_rows
                MODIFY raw_inn VARCHAR(255) NULL,
                MODIFY raw_product_name VARCHAR(255) NULL,
                MODIFY raw_winner VARCHAR(255) NULL,
                MODIFY raw_company_name VARCHAR(255) NULL,
                MODIFY error_message TEXT NULL');
        }
    }
};

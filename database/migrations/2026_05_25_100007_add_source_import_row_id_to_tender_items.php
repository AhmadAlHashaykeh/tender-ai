<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tender_items', function (Blueprint $table) {
            $table->foreignId('source_import_row_id')->nullable()->after('tender_id')
                ->constrained('import_rows')->nullOnDelete();
            $table->unique('source_import_row_id');
        });
    }

    public function down(): void
    {
        Schema::table('tender_items', function (Blueprint $table) {
            $table->dropUnique(['source_import_row_id']);
            $table->dropForeign(['source_import_row_id']);
            $table->dropColumn('source_import_row_id');
        });
    }
};

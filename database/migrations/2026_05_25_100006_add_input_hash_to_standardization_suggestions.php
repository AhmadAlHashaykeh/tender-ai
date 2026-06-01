<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standardization_suggestions', function (Blueprint $table) {
            $table->string('input_hash', 64)->nullable()->after('import_row_id');
            $table->unique('input_hash');
        });
    }

    public function down(): void
    {
        Schema::table('standardization_suggestions', function (Blueprint $table) {
            $table->dropUnique(['input_hash']);
            $table->dropColumn('input_hash');
        });
    }
};

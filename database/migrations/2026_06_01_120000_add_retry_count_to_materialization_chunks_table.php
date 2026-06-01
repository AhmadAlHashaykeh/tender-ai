<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materialization_chunks', function (Blueprint $table) {
            $table->unsignedSmallInteger('retry_count')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('materialization_chunks', function (Blueprint $table) {
            $table->dropColumn('retry_count');
        });
    }
};

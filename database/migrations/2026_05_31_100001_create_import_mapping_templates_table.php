<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mapping_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('mapping');
            $table->json('column_aliases')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_templates');
    }
};

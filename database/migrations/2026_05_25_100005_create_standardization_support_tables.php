<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standardization_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_row_id')->nullable()->constrained('import_rows')->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('suggested_standardized_drug_id')->nullable()
                ->constrained('standardized_drugs', 'id', 'std_sugg_drug_fk')
                ->nullOnDelete();
            $table->foreignId('suggested_company_id')->nullable()
                ->constrained('companies', 'id', 'std_sugg_company_fk')
                ->nullOnDelete();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('source')->default('rule');
            $table->json('suggestion_data')->nullable();
            $table->text('rationale')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'status']);
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->foreign('standardization_suggestion_id', 'import_rows_std_sugg_fk')
                ->references('id')
                ->on('standardization_suggestions')
                ->nullOnDelete();
        });

        Schema::create('standardization_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_row_id')->nullable()->constrained('import_rows')->nullOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('system');
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropForeign(['standardization_suggestion_id']);
        });

        Schema::dropIfExists('standardization_logs');
        Schema::dropIfExists('standardization_suggestions');
    }
};

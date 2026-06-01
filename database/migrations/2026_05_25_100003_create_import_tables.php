<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('filename');
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->string('status')->default('pending');
            $table->string('source_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('row_hash', 64)->index();

            $table->string('raw_code')->nullable();
            $table->string('raw_inn')->nullable();
            $table->string('raw_product_name')->nullable();
            $table->string('raw_country')->nullable();
            $table->string('raw_tender_number')->nullable();
            $table->string('raw_awarded_price')->nullable();
            $table->string('raw_price_usd')->nullable();
            $table->string('raw_winner')->nullable();
            $table->string('raw_company_name')->nullable();
            $table->string('raw_version')->nullable();
            $table->string('raw_year')->nullable();
            $table->string('raw_qty')->nullable();
            $table->string('raw_tender_value')->nullable();

            $table->json('raw_data');
            $table->json('normalized_data')->nullable();

            $table->string('validation_status')->default('pending');
            $table->string('standardization_status')->default('pending');
            $table->string('row_type')->default('winning_bid');

            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->decimal('drug_confidence', 5, 2)->nullable();
            $table->decimal('company_confidence', 5, 2)->nullable();
            $table->decimal('tender_confidence', 5, 2)->nullable();

            $table->text('error_message')->nullable();
            $table->json('warning_messages')->nullable();

            $table->foreignId('standardized_drug_id')->nullable()->constrained('standardized_drugs')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->unsignedBigInteger('tender_id')->nullable();
            $table->unsignedBigInteger('tender_item_id')->nullable();
            $table->unsignedBigInteger('bid_record_id')->nullable();

            $table->boolean('ai_assisted')->default(false);
            $table->unsignedBigInteger('standardization_suggestion_id')->nullable();

            $table->timestamps();

            $table->index(['import_batch_id', 'row_number']);
            $table->index('standardization_status');
            $table->index('validation_status');
        });

        Schema::create('import_row_duplicates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_row_id')->constrained('import_rows')->cascadeOnDelete();
            $table->foreignId('duplicate_import_row_id')->constrained('import_rows')->cascadeOnDelete();
            $table->string('match_type')->default('row_hash');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('resolution_status')->default('pending');
            $table->timestamps();

            $table->unique(['import_row_id', 'duplicate_import_row_id'], 'import_row_dup_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_duplicates');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};

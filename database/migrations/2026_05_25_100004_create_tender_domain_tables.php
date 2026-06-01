<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->string('tender_number');
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('version')->nullable();
            $table->string('title')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tender_number', 'country_id']);
            $table->index(['country_id', 'year']);
            $table->index(['tender_number', 'country_id', 'year', 'version'], 'tenders_identity_index');
        });

        Schema::create('tender_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained('tenders')->cascadeOnDelete();
            $table->foreignId('standardized_drug_id')->nullable()->constrained('standardized_drugs')->nullOnDelete();
            $table->unsignedInteger('line_number')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('quantity_unit')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tender_id', 'standardized_drug_id']);
        });

        Schema::create('bid_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_item_id')->nullable()->constrained('tender_items')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('standardized_drug_id')->nullable()->constrained('standardized_drugs')->nullOnDelete();
            $table->foreignId('tender_id')->nullable()->constrained('tenders')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();

            $table->string('bid_status')->default('awarded');
            $table->boolean('is_winner')->default(false);
            $table->string('row_type')->default('winning_bid');

            $table->decimal('price_usd', 18, 6)->nullable();
            $table->decimal('original_awarded_price', 18, 6)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('tender_value', 18, 4)->nullable();
            $table->unsignedSmallInteger('award_year')->nullable();

            $table->foreignId('source_import_row_id')->nullable()->constrained('import_rows')->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();

            $table->boolean('is_analytics_ready')->default(false);
            $table->boolean('excluded_from_stats')->default(false);
            $table->string('exclusion_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['standardized_drug_id', 'country_id']);
            $table->index(['bid_status', 'is_winner']);
            $table->index('price_usd');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->foreign('tender_id')->references('id')->on('tenders')->nullOnDelete();
            $table->foreign('tender_item_id')->references('id')->on('tender_items')->nullOnDelete();
            $table->foreign('bid_record_id')->references('id')->on('bid_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropForeign(['tender_id']);
            $table->dropForeign(['tender_item_id']);
            $table->dropForeign(['bid_record_id']);
        });

        Schema::dropIfExists('bid_records');
        Schema::dropIfExists('tender_items');
        Schema::dropIfExists('tenders');
    }
};

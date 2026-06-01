<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standardized_drug_id')->constrained('standardized_drugs')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->unsignedInteger('award_count')->default(0);
            $table->decimal('last_unit_price', 18, 6)->nullable();
            $table->decimal('avg_unit_price', 18, 6)->nullable();
            $table->decimal('weighted_avg_unit_price', 18, 6)->nullable();
            $table->decimal('median_unit_price', 18, 6)->nullable();
            $table->decimal('min_unit_price', 18, 6)->nullable();
            $table->decimal('max_unit_price', 18, 6)->nullable();
            $table->decimal('price_std_dev', 18, 6)->nullable();
            $table->date('last_award_date')->nullable();
            $table->string('trend_direction')->nullable();
            $table->decimal('trend_pct', 8, 4)->nullable();

            $table->foreignId('top_winner_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->unsignedInteger('distinct_winners_count')->default(0);
            $table->string('stats_version')->default('v1');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['standardized_drug_id', 'country_id'], 'pricing_stats_drug_country_idx');
            $table->index(['standardized_drug_id', 'region_id'], 'pricing_stats_drug_region_idx');
        });

        Schema::create('cached_market_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->foreignId('standardized_drug_id')->nullable()->constrained('standardized_drugs')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('scope')->default('drug_country');
            $table->json('statistics_payload');
            $table->string('stats_version')->default('v1');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['standardized_drug_id', 'country_id', 'region_id'], 'cached_market_scope_idx');
        });

        Schema::create('outlier_flags', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('flag_type');
            $table->string('severity')->default('medium');
            $table->text('reason')->nullable();
            $table->decimal('deviation_score', 8, 4)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('is_resolved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlier_flags');
        Schema::dropIfExists('cached_market_statistics');
        Schema::dropIfExists('pricing_statistics');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tender_id')->nullable()->constrained('tenders')->nullOnDelete();
            $table->foreignId('standardized_drug_id')->constrained('standardized_drugs')->cascadeOnDelete();

            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('quantity_unit')->nullable();
            $table->decimal('recommended_price', 18, 6)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('win_probability', 5, 2)->nullable();
            $table->string('risk_level')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('source')->default('backend');

            $table->string('context_hash', 64)->nullable()->index();
            $table->decimal('backend_recommended_price', 18, 6)->nullable();
            $table->boolean('openai_called')->default(false);
            $table->string('calculation_model_version')->nullable();
            $table->string('stats_version')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->string('ai_model')->nullable();
            $table->string('ai_prompt_hash', 64)->nullable();
            $table->json('ai_response_raw')->nullable();
            $table->text('rationale')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('prediction_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->decimal('last_winning_price', 18, 6)->nullable();
            $table->decimal('average_price', 18, 6)->nullable();
            $table->decimal('weighted_average_price', 18, 6)->nullable();
            $table->decimal('median_price', 18, 6)->nullable();
            $table->decimal('min_price', 18, 6)->nullable();
            $table->decimal('max_price', 18, 6)->nullable();
            $table->decimal('recommended_price', 18, 6)->nullable();
            $table->string('price_trend')->nullable();
            $table->decimal('trend_pct', 8, 4)->nullable();
            $table->decimal('quantity_factor', 8, 4)->nullable();
            $table->string('competition_level')->nullable();
            $table->decimal('competition_score', 8, 4)->nullable();
            $table->unsignedInteger('outlier_count')->default(0);
            $table->unsignedInteger('historical_award_count')->default(0);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('calculation_model_version')->nullable();
            $table->json('calculation_details')->nullable();
            $table->timestamps();

            $table->unique('prediction_id');
        });

        Schema::create('prediction_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->string('scenario_name');
            $table->decimal('recommended_price', 18, 6)->nullable();
            $table->decimal('win_probability', 5, 2)->nullable();
            $table->string('risk_level')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->json('metadata')->nullable();
            $table->text('rationale')->nullable();
            $table->string('source')->default('backend_template');
            $table->timestamps();
        });

        Schema::create('prediction_context_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->string('snapshot_hash', 64)->index();
            $table->json('snapshot_data');
            $table->string('stats_version')->nullable();
            $table->timestamps();
        });

        Schema::create('prediction_historical_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->foreignId('bid_record_id')->nullable()->constrained('bid_records')->nullOnDelete();
            $table->foreignId('tender_id')->nullable()->constrained('tenders')->nullOnDelete();
            $table->foreignId('standardized_drug_id')->nullable()->constrained('standardized_drugs')->nullOnDelete();
            $table->decimal('reference_price_usd', 18, 6)->nullable();
            $table->decimal('weight', 8, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('prediction_accuracy_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->decimal('predicted_price', 18, 6)->nullable();
            $table->decimal('actual_price', 18, 6)->nullable();
            $table->decimal('price_error_pct', 8, 4)->nullable();
            $table->boolean('won')->nullable();
            $table->string('outcome_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature');
            $table->string('provider')->default('openai');
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            $table->string('request_hash', 64)->nullable()->index();
            $table->string('status')->default('success');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('prediction_accuracy_records');
        Schema::dropIfExists('prediction_historical_refs');
        Schema::dropIfExists('prediction_context_snapshots');
        Schema::dropIfExists('prediction_scenarios');
        Schema::dropIfExists('prediction_calculations');
        Schema::dropIfExists('predictions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prediction_calculations', 'step_name')) {
            $this->upgradePredictionScenarios();

            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->recreatePredictionCalculationsForSqlite();
        } else {
            Schema::table('prediction_calculations', function (Blueprint $table) {
                $table->dropIndex('prediction_calculations_prediction_id_step_order_index');
            });

            Schema::table('prediction_calculations', function (Blueprint $table) {
                $table->dropColumn(['step_name', 'step_order', 'input_data', 'output_data', 'duration_ms']);
            });

            Schema::table('prediction_calculations', function (Blueprint $table) {
                $table->decimal('last_winning_price', 18, 6)->nullable()->after('prediction_id');
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

                $table->unique('prediction_id');
            });
        }

        $this->upgradePredictionScenarios();
    }

    public function down(): void
    {
        // Irreversible schema reshape for Phase 6A.
    }

    protected function recreatePredictionCalculationsForSqlite(): void
    {
        Schema::drop('prediction_calculations');

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
    }

    protected function upgradePredictionScenarios(): void
    {
        if (Schema::hasColumn('prediction_scenarios', 'recommended_price')) {
            return;
        }

        Schema::table('prediction_scenarios', function (Blueprint $table) {
            $table->decimal('recommended_price', 18, 6)->nullable()->after('scenario_name');
            $table->text('rationale')->nullable()->after('risk_level');
            $table->string('source')->default('backend_template')->after('rationale');
        });

        if (Schema::hasColumn('prediction_scenarios', 'price')) {
            DB::table('prediction_scenarios')
                ->whereNotNull('price')
                ->update(['recommended_price' => DB::raw('price')]);

            Schema::table('prediction_scenarios', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->text('ai_narrative')->nullable()->after('ai_response_raw');
            $table->timestamp('ai_narrative_generated_at')->nullable()->after('ai_narrative');
            $table->string('ai_model_used')->nullable()->after('ai_narrative_generated_at');
            $table->unsignedInteger('ai_tokens_used')->nullable()->after('ai_model_used');
            $table->unsignedInteger('ai_response_ms')->nullable()->after('ai_tokens_used');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignId('prediction_id')
                ->nullable()
                ->after('user_id')
                ->constrained('predictions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prediction_id');
        });

        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn([
                'ai_narrative',
                'ai_narrative_generated_at',
                'ai_model_used',
                'ai_tokens_used',
                'ai_response_ms',
            ]);
        });
    }
};

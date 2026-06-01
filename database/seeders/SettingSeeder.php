<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'general.organization_name', 'value' => 'TenderAI', 'type' => 'string', 'group' => 'general', 'description' => 'Organization display name'],
            ['key' => 'general.default_currency', 'value' => 'USD', 'type' => 'string', 'group' => 'general', 'description' => 'Default currency code'],
            ['key' => 'general.date_format', 'value' => 'Y-m-d', 'type' => 'string', 'group' => 'general', 'description' => 'PHP date format for displays'],
            ['key' => 'general.rows_per_page', 'value' => '25', 'type' => 'integer', 'group' => 'general', 'description' => 'Default table page size'],
            ['key' => 'general.timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'general', 'description' => 'Application timezone'],
            ['key' => 'general.language', 'value' => 'en', 'type' => 'string', 'group' => 'general', 'description' => 'System interface language'],

            // Prediction
            ['key' => 'prediction.calculation_model_version', 'value' => 'v1.0', 'type' => 'string', 'group' => 'prediction', 'description' => 'Active backend prediction model version'],
            ['key' => 'prediction.backend_only_confidence_threshold', 'value' => '80', 'type' => 'integer', 'group' => 'prediction', 'description' => 'Minimum confidence to skip OpenAI escalation'],
            ['key' => 'prediction.trend_adjustment_cap', 'value' => '7', 'type' => 'float', 'group' => 'prediction', 'description' => 'Maximum trend adjustment percent'],
            ['key' => 'prediction.aggressive_discount_percent', 'value' => '3', 'type' => 'float', 'group' => 'prediction', 'description' => 'Aggressive scenario discount percent'],
            ['key' => 'prediction.conservative_premium_percent', 'value' => '3', 'type' => 'float', 'group' => 'prediction', 'description' => 'Conservative scenario premium percent'],
            ['key' => 'prediction.large_quantity_multiplier', 'value' => '2', 'type' => 'float', 'group' => 'prediction', 'description' => 'Large quantity threshold multiplier vs median'],
            ['key' => 'prediction.large_quantity_discount_percent', 'value' => '3', 'type' => 'float', 'group' => 'prediction', 'description' => 'Discount percent for large quantities'],
            ['key' => 'prediction.small_quantity_multiplier', 'value' => '0.5', 'type' => 'float', 'group' => 'prediction', 'description' => 'Small quantity threshold multiplier vs median'],
            ['key' => 'prediction.small_quantity_premium_percent', 'value' => '3', 'type' => 'float', 'group' => 'prediction', 'description' => 'Premium percent for small quantities'],

            // Standardization
            ['key' => 'standardization.drug_auto_approve_min', 'value' => '85', 'type' => 'integer', 'group' => 'standardization', 'description' => 'Minimum confidence for automatic drug standardization approval'],
            ['key' => 'standardization.company_auto_approve_min', 'value' => '85', 'type' => 'integer', 'group' => 'standardization', 'description' => 'Minimum confidence for automatic company standardization approval'],
            ['key' => 'standardization.row_auto_approve_min', 'value' => '75', 'type' => 'integer', 'group' => 'standardization', 'description' => 'Minimum tender confidence for automatic import row approval'],
            ['key' => 'standardization.ai_auto_approve_min', 'value' => '85', 'type' => 'integer', 'group' => 'standardization', 'description' => 'Minimum AI suggestion confidence for auto approval'],
            ['key' => 'standardization.fuzzy_auto_approve_min', 'value' => '80', 'type' => 'integer', 'group' => 'standardization', 'description' => 'Minimum fuzzy match score for auto approval'],
            ['key' => 'standardization.max_ai_calls_per_batch', 'value' => '50', 'type' => 'integer', 'group' => 'standardization', 'description' => 'Maximum AI calls per standardization batch'],
            ['key' => 'standardization.enable_ai_assist', 'value' => '0', 'type' => 'boolean', 'group' => 'standardization', 'description' => 'Enable AI-assisted standardization'],

            // AI / OpenAI
            ['key' => 'ai.provider', 'value' => 'openai', 'type' => 'string', 'group' => 'ai', 'description' => 'Default AI provider'],
            ['key' => 'ai.default_model', 'value' => 'gpt-4o-mini', 'type' => 'string', 'group' => 'ai', 'description' => 'Default OpenAI model'],
            ['key' => 'ai.advanced_model', 'value' => 'gpt-4o', 'type' => 'string', 'group' => 'ai', 'description' => 'Advanced OpenAI model'],
            ['key' => 'ai.temperature', 'value' => '0.2', 'type' => 'float', 'group' => 'ai', 'description' => 'Model temperature'],
            ['key' => 'ai.max_tokens', 'value' => '800', 'type' => 'integer', 'group' => 'ai', 'description' => 'Max tokens per request'],
            ['key' => 'ai.timeout_seconds', 'value' => '60', 'type' => 'integer', 'group' => 'ai', 'description' => 'HTTP timeout for AI requests'],
            ['key' => 'ai.enable_narrative', 'value' => '0', 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI narrative generation'],
            ['key' => 'ai.narrative_min_confidence', 'value' => '50', 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum confidence required for AI prediction narratives'],
            ['key' => 'ai.enable_standardization_assist', 'value' => '0', 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI standardization assist'],
            ['key' => 'ai.rate_limit_per_user_per_hour', 'value' => '10', 'type' => 'integer', 'group' => 'ai', 'description' => 'Rate limit per user per hour'],
            ['key' => 'ai.monthly_token_budget', 'value' => '', 'type' => 'integer', 'group' => 'ai', 'description' => 'Optional monthly token budget'],
            ['key' => 'ai.system_prompt_version', 'value' => 'v1.0', 'type' => 'string', 'group' => 'ai', 'description' => 'System prompt template version'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}

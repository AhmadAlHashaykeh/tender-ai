<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Http;

class OpenAiConnectionTestService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * @return array{success: bool, message: string}
     */
    public function test(): array
    {
        $apiKey = $this->settings->getEncrypted('ai.api_key');

        if (! filled($apiKey)) {
            return [
                'success' => false,
                'message' => 'No API key configured. Save an OpenAI API key first.',
            ];
        }

        $model = $this->settings->getString('ai.default_model', 'gpt-4o-mini');
        $timeout = $this->settings->getInteger('ai.timeout_seconds', 60) ?? 60;

        try {
            $response = Http::withToken($apiKey)
                ->timeout(min($timeout, 30))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with OK only.'],
                    ],
                    'max_tokens' => 5,
                    'temperature' => 0,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'OpenAI connection successful.',
                ];
            }

            $error = $response->json('error.message') ?? $response->reason();

            return [
                'success' => false,
                'message' => 'OpenAI API error: '.$error,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }
}

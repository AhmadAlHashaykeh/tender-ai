<?php

namespace App\Enums;

enum RecommendationMode: string
{
    case Calculation = 'calculation';
    case AiAssisted = 'ai_assisted';

    public function label(): string
    {
        return match ($this) {
            self::Calculation => 'Business Calculation',
            self::AiAssisted => 'AI-Assisted Analysis',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Calculation => 'Uses historical tender awards, market statistics, and pricing rules to calculate a recommended bid price.',
            self::AiAssisted => 'Uses the same historical tender data and calculations, then adds AI-powered market interpretation and business insights. AI never changes the calculated price.',
        };
    }
}

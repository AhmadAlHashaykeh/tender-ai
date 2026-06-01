<?php

namespace App\Enums;

enum PredictionSource: string
{
    case BackendOnly = 'backend_only';
    case BackendTemplate = 'backend_template';
    case AiAssisted = 'ai_assisted';

    public function label(): string
    {
        return match ($this) {
            self::BackendOnly => 'Calculation only',
            self::BackendTemplate => 'Calculated scenario',
            self::AiAssisted => 'AI-assisted',
        };
    }
}

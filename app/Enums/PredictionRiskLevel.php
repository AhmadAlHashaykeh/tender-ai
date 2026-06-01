<?php

namespace App\Enums;

enum PredictionRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}

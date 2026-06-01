<?php

namespace App\Enums;

enum PredictionScenarioType: string
{
    case Aggressive = 'aggressive';
    case Balanced = 'balanced';
    case Conservative = 'conservative';
}

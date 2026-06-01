<?php

namespace App\Enums;

enum TrendDirection: string
{
    case Rising = 'rising';
    case Stable = 'stable';
    case Falling = 'falling';
    case Unknown = 'unknown';
}

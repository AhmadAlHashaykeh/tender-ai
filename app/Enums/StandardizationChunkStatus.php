<?php

namespace App\Enums;

enum StandardizationChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

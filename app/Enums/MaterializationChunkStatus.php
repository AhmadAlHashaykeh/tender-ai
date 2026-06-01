<?php

namespace App\Enums;

enum MaterializationChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

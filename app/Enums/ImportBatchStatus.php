<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case AwaitingMapping = 'awaiting_mapping';
    case Queued = 'queued';
    case Processing = 'processing';
    case Parsing = 'parsing';
    case Validating = 'validating';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
}

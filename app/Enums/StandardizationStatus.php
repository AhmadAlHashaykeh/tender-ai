<?php

namespace App\Enums;

enum StandardizationStatus: string
{
    case Pending = 'pending';
    case AutoApproved = 'auto_approved';
    case Approved = 'approved';
    case ReviewRequired = 'review_required';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
}

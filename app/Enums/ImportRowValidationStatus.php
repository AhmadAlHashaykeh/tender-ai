<?php

namespace App\Enums;

enum ImportRowValidationStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Warning = 'warning';
    case Duplicate = 'duplicate';
}

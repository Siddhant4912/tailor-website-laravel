<?php

namespace App\Enums;

enum ReportStatusEnum: string
{
    case OPEN = 'open';
    case REVIEWED = 'reviewed';
    case DISMISSED = 'dismissed';
}

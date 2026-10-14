<?php

namespace App\Enum;

enum TaskStateEnum: string
{
    case ALL = 'all';
    case IN_PROGRESS = 'in_progress';
    case LATE = 'late';
    case DONE = 'done';
}

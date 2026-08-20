<?php
namespace App\Enums;

enum TaskStatus: string {
    case QUEUED = 'QUEUED';
    case LEASED = 'LEASED';
    case RUNNING = 'RUNNING';
    case RETRY_WAIT = 'RETRY_WAIT';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}

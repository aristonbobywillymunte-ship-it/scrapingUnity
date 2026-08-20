<?php
namespace App\Enums;

enum RunStatus: string {
    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case PARTIAL = 'PARTIAL';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model {
    protected $table = 'tasks';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false; // We manage timestamps manually
    
    // Statuses
    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_LEASED = 'LEASED';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_RETRY_WAIT = 'RETRY_WAIT';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
}

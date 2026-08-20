<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TaskAttempt extends Model {
    protected $table = 'task_attempts';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false;
}

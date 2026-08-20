<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TaskLease extends Model {
    protected $table = 'task_leases';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false;
}

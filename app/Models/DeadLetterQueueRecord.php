<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DeadLetterQueueRecord extends Model {
    protected $table = 'dead_letter_queue_records';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false;
}

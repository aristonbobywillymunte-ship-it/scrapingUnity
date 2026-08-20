<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RunRequest extends Model {
    protected $table = 'run_requests';
    protected $primaryKey = 'run_id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false;
}

<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RunResult extends Model {
    protected $table = 'run_results';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    public $timestamps = false;
}

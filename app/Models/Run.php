<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Run extends Model {
    protected $table = 'runs';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    const UPDATED_AT = null;

    public function request() {
        return $this->hasOne(RunRequest::class, 'run_id', 'id');
    }
}

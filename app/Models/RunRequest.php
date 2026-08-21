<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RunRequest extends Model {
    protected $table = 'run_requests';
    protected $primaryKey = 'run_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'request_snapshot' => 'array',
    ];
}

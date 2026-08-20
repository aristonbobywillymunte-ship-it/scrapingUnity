<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtpRateBucket extends Model {
    public $incrementing = false;
    protected $primaryKey = ['user_id', 'channel', 'bucket_date'];
    public $timestamps = true;
    const CREATED_AT = null;
    protected $fillable = ['user_id', 'channel', 'bucket_date', 'request_count'];
}

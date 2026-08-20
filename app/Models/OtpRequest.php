<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class OtpRequest extends Model {
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;
    protected $fillable = ['user_id', 'channel', 'otp_hash', 'purpose', 'attempt_count', 'expires_at', 'used_at'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}

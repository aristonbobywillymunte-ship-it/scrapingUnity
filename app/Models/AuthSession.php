<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuthSession extends Model
{
    use HasUuids;

    protected $table = 'auth_sessions';
    public $incrementing = false;
    protected $keyType = 'string';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'token_hash',
        'device_metadata',
        'ip_address',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'device_metadata' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}

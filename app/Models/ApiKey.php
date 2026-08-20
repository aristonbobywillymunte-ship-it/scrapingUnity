<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class ApiKey extends Model {
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;
    protected $fillable = ['organization_id', 'created_by', 'key_hash', 'key_prefix', 'scopes', 'status', 'last_used_at', 'revoked_at'];
    protected $casts = ['scopes' => 'array', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
}

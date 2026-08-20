<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class ChannelBinding extends Model {
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['user_id', 'channel', 'external_identity', 'verified_at', 'status', 'revoked_at', 'safe_metadata'];
    protected $casts = ['verified_at' => 'datetime', 'revoked_at' => 'datetime', 'safe_metadata' => 'array'];
}

<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasUuids;

    protected $table = 'users';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'password_hash',
        'mfa_enabled',
        'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function organizationMemberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function getAuthPasswordName()
    {
        return 'password_hash';
    }
}

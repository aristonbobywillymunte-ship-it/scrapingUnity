<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class OrganizationMembership extends Model {
    use HasUuids;
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $fillable = ['organization_id', 'user_id', 'role_id', 'role_is_internal'];
}

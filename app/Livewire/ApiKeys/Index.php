<?php

namespace App\Livewire\ApiKeys;

use Livewire\Component;
use App\Models\ApiKey;
use Illuminate\Support\Str;
use App\Services\AuditSecurityService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Index extends Component
{
    public $name = '';
    public $newKey = null;

    public function createKey()
    {
        $this->validate(['name' => 'required|string|max:255']);
        
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        if (!$orgId) return;

        $rawKey = Str::random(40);
        $keyHash = hash('sha256', $rawKey);
        
        $apiKey = ApiKey::create([
            'id' => Str::uuid(),
            'organization_id' => $orgId,
            'created_by' => auth()->user()->id,
            'name' => $this->name,
            'key_hash' => $keyHash,
            'key_prefix' => substr($rawKey, 0, 8),
            'scopes' => json_encode(['*']),
            'status' => 'ACTIVE'
        ]);
        
        AuditSecurityService::log('API_KEY_CREATED', auth()->user()->id, $orgId, ['key_id' => $apiKey->id]);
        
        $this->newKey = $rawKey;
        $this->name = '';
    }
    
    public function revokeKey($id)
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        $key = ApiKey::where('organization_id', $orgId)->where('id', $id)->first();
        if ($key) {
            $key->update(['status' => 'REVOKED']);
            AuditSecurityService::log('API_KEY_REVOKED', auth()->user()->id, $orgId, ['key_id' => $id]);
        }
    }

    public function render()
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        $keys = $orgId ? ApiKey::where('organization_id', $orgId)->orderBy('created_at', 'desc')->get() : collect();
        
        return view('livewire.api-keys.index', [
            'keys' => $keys
        ]);
    }
}

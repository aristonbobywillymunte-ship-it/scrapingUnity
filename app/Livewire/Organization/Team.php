<?php
namespace App\Livewire\Organization;

use Livewire\Component;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Illuminate\Support\Str;

#[Layout('layouts.app')]
class Team extends Component
{
    public $email = '';
    public $role = 'member';
    public $message = '';

    public function inviteMember()
    {
        $this->validate([
            'email' => 'required|email'
        ]);

        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        if (!$orgId) return;

        DB::transaction(function() use ($orgId) {
            $user = User::where('email', $this->email)->first();
            if (!$user) {
                $user = User::create([
                    'id' => Str::uuid(),
                    'email' => $this->email,
                    'password_hash' => Hash::make(Str::random(16)),
                    'status' => 'ACTIVE'
                ]);
            }
            
            OrganizationMembership::updateOrCreate(
                ['user_id' => $user->id, 'organization_id' => $orgId],
                ['id' => Str::uuid(), 'role_id' => $this->role]
            );
        });
        
        $this->message = 'User invited successfully.';
        $this->email = '';
    }

    public function render()
    {
        $orgId = request()->header('X-Organization-Id') ?? auth()->user()->organizationMemberships()->first()?->organization_id;
        
        $members = collect();
        if ($orgId) {
            $members = OrganizationMembership::where('organization_id', $orgId)
                ->with('user')
                ->get();
        }

        return view('livewire.organization.team', [
            'members' => $members
        ]);
    }
}

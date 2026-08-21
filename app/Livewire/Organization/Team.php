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
            // Ensure the inviter has owner or admin role
            $inviterMembership = OrganizationMembership::where('user_id', auth()->id())
                ->where('organization_id', $orgId)
                ->first();

            if (!$inviterMembership || !in_array($inviterMembership->role_id, ['owner', 'admin'])) {
                $this->message = 'You do not have permission to invite members.';
                return;
            }

            // Validate role
            $validRole = DB::table('roles')
                ->where('id', $this->role)
                ->where('is_internal_role', false)
                ->first();

            if (!$validRole) {
                $this->message = 'Invalid or internal role selected.';
                return;
            }

            $user = User::where('email', $this->email)->first();
            if (!$user) {
                $this->message = 'Invitation sent (simulated).';
                return;
            }
            
            OrganizationMembership::updateOrCreate(
                ['user_id' => $user->id, 'organization_id' => $orgId],
                ['id' => Str::uuid(), 'role_id' => $this->role]
            );
            $this->message = 'User invited successfully.';
        });
        
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

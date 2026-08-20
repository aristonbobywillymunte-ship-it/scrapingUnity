<?php
namespace App\Services;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class RunPreflightService {
    public function validate($user, $orgId, $capability) {
        if (!$user || $user->status !== 'ACTIVE') throw new \Exception('Actor inactive');
        $org = Organization::find($orgId);
        if (!$org || $org->status !== 'ACTIVE') throw new \Exception('Organization inactive');
        
        $hasMembership = DB::table('organization_memberships')
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->exists();
        if (!$hasMembership) throw new \Exception('Unauthorized actor');
        
        if (!CapabilityRegistry::isValid($capability)) throw new \Exception('Unsupported capability');
        
        $maintenance = DB::table('system_maintenance')->where('status', 'ACTIVE')->exists();
        if ($maintenance) throw new \Exception('Maintenance blocked');
        
        return true;
    }
}

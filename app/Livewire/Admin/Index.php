<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Organization;
use App\Models\Run;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Layout('layouts.app')]
class Index extends Component {
    public $email = '';
    public $password = '';
    public $initialCredits = 500;
    public $search = '';
    public $successMessage = '';
    public $errorMessage = '';

    public function mount() {
        $user = auth()->user();
        $isInternal = DB::table('internal_user_assignments')
            ->where('user_id', $user?->id)
            ->exists();
        $isPlatformAdmin = $user?->email === 'admin@example.com';
            
        if (!$isInternal && !$isPlatformAdmin) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function createUser() {
        $this->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'initialCredits' => 'required|integer|min:0|max:100000',
        ]);

        try {
            DB::beginTransaction();

            $userId = (string) Str::uuid();
            $user = User::create([
                'id' => $userId,
                'email' => strtolower(trim($this->email)),
                'password_hash' => Hash::make($this->password),
                'status' => 'ACTIVE',
            ]);

            // Create default organization
            $orgId = (string) Str::uuid();
            $org = Organization::create([
                'id' => $orgId,
                'name' => 'Org ' . explode('@', $this->email)[0],
                'status' => 'ACTIVE',
            ]);

            // Ensure owner role exists
            DB::table('roles')->insertOrIgnore([
                ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]
            ]);

            // Bind membership
            DB::table('organization_memberships')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'role_id' => 'owner',
            ]);

            // Assign initial quota
            if ($this->initialCredits > 0) {
                $lotId = (string) Str::uuid();
                DB::table('credit_lots')->insert([
                    'id' => $lotId,
                    'organization_id' => $org->id,
                    'original_quantity' => (float) $this->initialCredits,
                    'remaining_quantity' => (float) $this->initialCredits,
                    'source' => 'PACKAGE',
                    'expires_at' => now()->addYear(),
                ]);

                DB::table('credit_ledger')->insert([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $org->id,
                    'transaction_type' => 'PACKAGE_CREDIT',
                    'credit_lot_id' => $lotId,
                    'quantity' => (float) $this->initialCredits,
                    'event_idempotency_key' => (string) Str::uuid(),
                    'created_at' => now(),
                ]);
            }

            // Log security event
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => auth()->id(),
                'action' => 'USER_PROVISIONED',
                'target_resource' => 'users:' . $user->id,
                'payload' => json_encode(['email' => $user->email, 'quota' => $this->initialCredits]),
                'created_at' => now(),
            ]);

            DB::commit();

            $this->reset(['email', 'password', 'initialCredits']);
            $this->initialCredits = 500;
            $this->successMessage = 'Pengguna berhasil dibuat dan diaktivasi.';
            $this->errorMessage = '';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorMessage = 'Gagal membuat pengguna: ' . $e->getMessage();
            $this->successMessage = '';
        }
    }

    public function toggleUserStatus($userId) {
        $targetUser = User::find($userId);
        if (!$targetUser || $targetUser->email === 'admin@example.com') {
            return;
        }

        $newStatus = $targetUser->status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE';
        $targetUser->update(['status' => $newStatus]);

        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'action' => 'USER_STATUS_CHANGED',
            'target_resource' => 'users:' . $targetUser->id,
            'payload' => json_encode(['new_status' => $newStatus]),
            'created_at' => now(),
        ]);

        $this->successMessage = "Status pengguna diperbarui menjadi {$newStatus}.";
    }

    public function render() {
        $usersQuery = User::query();
        if (!empty($this->search)) {
            $usersQuery->where('email', 'like', '%' . strtolower(trim($this->search)) . '%');
        }

        $users = $usersQuery->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.admin.index', [
            'totalUsers' => User::count(),
            'totalOrgs' => Organization::count(),
            'totalRuns' => Run::count(),
            'users' => $users,
        ]);
    }
}

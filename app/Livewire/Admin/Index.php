<?php
namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Organization;
use App\Models\Run;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\SanitizerService;

#[Layout('layouts.app')]
class Index extends Component {
    public $email = '';
    public $password = '';
    public $initialCredits = 500;
    public $search = '';
    public $successMessage = '';
    public $errorMessage = '';

    // P0-3: Confirmation state
    public $confirmingUserId = null;
    public $confirmingUserEmail = null;
    public $confirmingAction = null; // 'suspend' or 'activate'

    public function mount() {
        $this->authorizeAdmin();
    }

    // P0-4: Single canonical DB-backed Admin check — no hardcoded email bypass
    private function authorizeAdmin(): void {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }
        $isAdmin = DB::table('internal_user_assignments')
            ->where('user_id', $user->id)
            ->exists();
        if (!$isAdmin) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function createUser() {
        $this->authorizeAdmin();

        $this->validate([
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:8',
            'initialCredits' => 'required|integer|min:0|max:100000',
        ]);

        try {
            DB::beginTransaction();

            $userId = (string) Str::uuid();
            $user = User::create([
                'id'            => $userId,
                'email'         => strtolower(trim($this->email)),
                'password_hash' => Hash::make($this->password),
                'status'        => 'ACTIVE',
            ]);

            // Create default organization
            $orgId = (string) Str::uuid();
            $org = Organization::create([
                'id'     => $orgId,
                'name'   => 'Org ' . explode('@', $this->email)[0],
                'status' => 'ACTIVE',
            ]);

            // Ensure owner role exists (non-internal)
            DB::table('roles')->insertOrIgnore([
                ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]
            ]);

            // Bind membership
            DB::table('organization_memberships')->insert([
                'id'              => (string) Str::uuid(),
                'user_id'         => $user->id,
                'organization_id' => $org->id,
                'role_id'         => 'owner',
            ]);

            // Assign initial quota
            if ($this->initialCredits > 0) {
                $lotId = (string) Str::uuid();
                DB::table('credit_lots')->insert([
                    'id'                => $lotId,
                    'organization_id'   => $org->id,
                    'original_quantity' => (int) $this->initialCredits,
                    'remaining_quantity' => (int) $this->initialCredits,
                    'source'            => 'SUBSCRIPTION',
                    'effective_monetary_value_cents' => 0,
                    'created_at'        => now(),
                    'expires_at'        => now()->addYear(),
                ]);

                DB::table('credit_ledger')->insert([
                    'id'                     => (string) Str::uuid(),
                    'organization_id'        => $org->id,
                    'transaction_type'       => 'PACKAGE_CREDIT',
                    'credit_lot_id'          => $lotId,
                    'quantity'               => (int) $this->initialCredits,
                    'event_idempotency_key'  => (string) Str::uuid(),
                    'created_at'             => now(),
                ]);
            }

            // P0-1: Audit log using correct schema columns
            DB::table('audit_logs')->insert([
                'id'         => (string) Str::uuid(),
                'actor_id'   => auth()->id(),
                'actor_type' => 'admin',
                'action'     => 'USER_PROVISIONED',
                'target'     => 'users:' . $user->id,
                'safe_metadata' => json_encode([
                    'email'           => $user->email,
                    'initial_credits' => $this->initialCredits,
                    'organization_id' => $org->id,
                ]),
                'created_at' => now(),
            ]);

            DB::commit();

            $this->reset(['email', 'password', 'initialCredits']);
            $this->initialCredits = 500;
            $this->successMessage = 'Pengguna berhasil dibuat dan diaktivasi.';
            $this->errorMessage   = '';
        } catch (\Exception $e) {
            DB::rollBack();
            // P0-5: Log full exception server-side; show only sanitized message to client
            Log::error('Admin::createUser failed: ' . SanitizerService::sanitizeException($e));
            $this->errorMessage   = 'Gagal membuat pengguna. Silakan coba lagi atau hubungi tim teknis.';
            $this->successMessage = '';
        }
    }

    // P0-3: Step 1 — show confirmation UI, do NOT act yet
    public function requestToggleUserStatus(string $userId) {
        $this->authorizeAdmin();

        $targetUser = User::find($userId);
        if (!$targetUser) {
            return;
        }

        $this->confirmingUserId    = $userId;
        $this->confirmingUserEmail = $targetUser->email;
        $this->confirmingAction    = $targetUser->status === 'ACTIVE' ? 'suspend' : 'activate';
    }

    // P0-3: Step 2 — actually execute after user confirmed
    public function confirmToggleUserStatus() {
        $this->authorizeAdmin();

        if (!$this->confirmingUserId) {
            return;
        }

        $targetUser = User::find($this->confirmingUserId);
        if (!$targetUser) {
            $this->cancelConfirmation();
            return;
        }

        $newStatus = $targetUser->status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE';
        $targetUser->update(['status' => $newStatus]);

        // P0-1: Audit log using correct schema columns
        try {
            DB::table('audit_logs')->insert([
                'id'         => (string) Str::uuid(),
                'actor_id'   => auth()->id(),
                'actor_type' => 'admin',
                'action'     => 'USER_STATUS_CHANGED',
                'target'     => 'users:' . $targetUser->id,
                'safe_metadata' => json_encode([
                    'new_status'   => $newStatus,
                    'target_email' => $targetUser->email,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Admin::confirmToggleUserStatus audit failed: ' . SanitizerService::sanitizeException($e));
        }

        $label = $newStatus === 'ACTIVE' ? 'diaktifkan' : 'ditangguhkan';
        $this->successMessage = "Pengguna {$this->confirmingUserEmail} berhasil {$label}.";
        $this->cancelConfirmation();
    }

    public function cancelConfirmation() {
        $this->confirmingUserId    = null;
        $this->confirmingUserEmail = null;
        $this->confirmingAction    = null;
    }

    public function render() {
        $usersQuery = User::query();
        if (!empty($this->search)) {
            $usersQuery->where('email', 'like', '%' . strtolower(trim($this->search)) . '%');
        }

        $users = $usersQuery->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.admin.index', [
            'totalUsers' => User::count(),
            'totalOrgs'  => Organization::count(),
            'totalRuns'  => Run::count(),
            'users'      => $users,
        ]);
    }
}

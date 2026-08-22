<?php
namespace App\Livewire\Admin\Users;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\SanitizerService;
use App\Livewire\Admin\Concerns\AuthorizesAdmin;

#[Layout('layouts.app')]
class Index extends Component {
    use WithPagination, AuthorizesAdmin;

    public $email = '';
    public $password = '';
    public $initialCredits = 500;
    public $search = '';
    public $statusFilter = '';
    public $successMessage = '';
    public $errorMessage = '';

    // Confirmation modal
    public $confirmingUserId = null;
    public $confirmingUserEmail = null;
    public $confirmingAction = null; // 'suspend', 'activate', 'disable'

    // Edit modal
    public $editingUserId = null;
    public $editEmail = '';
    public $editStatus = 'ACTIVE';
    public $editQuota = 0;

    public function mount() {
        $this->authorizeAdmin();
    }

    public function createUser() {
        $this->authorizeAdmin();

        $this->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'initialCredits' => 'required|integer|min:0|max:1000000',
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

            $orgId = (string) Str::uuid();
            $org = Organization::create([
                'id' => $orgId,
                'name' => 'Org ' . explode('@', $this->email)[0],
                'status' => 'ACTIVE',
            ]);

            DB::table('roles')->insertOrIgnore([
                ['id' => 'owner', 'description' => 'Owner', 'is_internal_role' => false]
            ]);

            DB::table('organization_memberships')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'role_id' => 'owner',
            ]);

            if ($this->initialCredits > 0) {
                $lotId = (string) Str::uuid();
                DB::table('credit_lots')->insert([
                    'id' => $lotId,
                    'organization_id' => $org->id,
                    'original_quantity' => (int) $this->initialCredits,
                    'remaining_quantity' => (int) $this->initialCredits,
                    'source' => 'SUBSCRIPTION',
                    'effective_monetary_value_cents' => 0,
                    'created_at' => now(),
                    'expires_at' => now()->addYear(),
                ]);

                DB::table('credit_ledger')->insert([
                    'id' => (string) Str::uuid(),
                    'organization_id' => $org->id,
                    'transaction_type' => 'PACKAGE_CREDIT',
                    'credit_lot_id' => $lotId,
                    'quantity' => (int) $this->initialCredits,
                    'event_idempotency_key' => (string) Str::uuid(),
                    'created_at' => now(),
                ]);
            }

            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => 'USER_PROVISIONED',
                'target' => 'users:' . $user->id,
                'safe_metadata' => json_encode([
                    'email' => $user->email,
                    'initial_credits' => $this->initialCredits,
                    'organization_id' => $org->id,
                ]),
                'created_at' => now(),
            ]);

            DB::commit();

            $this->reset(['email', 'password']);
            $this->initialCredits = 500;
            $this->successMessage = 'Pengguna berhasil didaftarkan dan diaktifkan.';
            $this->errorMessage = '';
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin::createUser failed: ' . SanitizerService::sanitizeException($e));
            $this->errorMessage = 'Gagal mendaftarkan pengguna: ' . SanitizerService::sanitizeException($e);
        }
    }

    public function requestStatusChange($userId, $action) {
        $this->authorizeAdmin();
        $targetUser = User::find($userId);
        if (!$targetUser) return;

        $this->confirmingUserId = $userId;
        $this->confirmingUserEmail = $targetUser->email;
        $this->confirmingAction = $action;
    }

    public function confirmStatusChange() {
        $this->authorizeAdmin();
        if (!$this->confirmingUserId) return;

        $targetUser = User::find($this->confirmingUserId);
        if (!$targetUser) {
            $this->cancelConfirmation();
            return;
        }

        $statusMap = [
            'suspend' => 'SUSPENDED',
            'activate' => 'ACTIVE',
            'disable' => 'DISABLED',
        ];

        $newStatus = $statusMap[$this->confirmingAction] ?? 'ACTIVE';
        $targetUser->update(['status' => $newStatus]);

        try {
            DB::table('audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => 'USER_STATUS_CHANGED',
                'target' => 'users:' . $targetUser->id,
                'safe_metadata' => json_encode([
                    'new_status' => $newStatus,
                    'target_email' => $targetUser->email,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {}

        $this->successMessage = "Status pengguna {$targetUser->email} diubah menjadi {$newStatus}.";
        $this->cancelConfirmation();
    }

    public function cancelConfirmation() {
        $this->confirmingUserId = null;
        $this->confirmingUserEmail = null;
        $this->confirmingAction = null;
    }

    public function render() {
        $query = User::query();
        if (!empty($this->search)) {
            $query->where('email', 'like', '%' . strtolower(trim($this->search)) . '%');
        }
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'totalUsers' => User::count(),
            'activeUsers' => User::where('status', 'ACTIVE')->count(),
            'suspendedUsers' => User::where('status', 'SUSPENDED')->count(),
            'disabledUsers' => User::where('status', 'DISABLED')->count(),
        ]);
    }
}

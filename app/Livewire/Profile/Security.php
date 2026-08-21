<?php
namespace App\Livewire\Profile;
use Livewire\Component;
use App\Models\AuthSession;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Security extends Component {
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';
    public $message = '';
    public $error = '';
    
    public function updatePassword() {
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed'
        ]);
        
        $user = auth()->user();
        if (!Hash::check($this->current_password, $user->password_hash)) {
            $this->error = 'Current password is incorrect.';
            return;
        }
        
        $user->update(['password_hash' => Hash::make($this->new_password)]);
        $this->message = 'Password updated successfully.';
        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->error = '';
    }
    
    public function revokeSession($sessionId) {
        $session = AuthSession::where('user_id', auth()->id())->where('id', $sessionId)->first();
        if ($session) {
            $session->update(['revoked_at' => now()]);
        }
    }

    public function render() { 
        return view('livewire.profile.security', [
            'sessions' => AuthSession::where('user_id', auth()->id())->whereNull('revoked_at')->get()
        ]); 
    }
}

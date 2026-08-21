<?php
namespace App\Livewire\Auth;
use Livewire\Component;
use App\Services\OtpService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class PasswordRecovery extends Component {
    public $email = '';
    public $channel = 'EMAIL';
    public $sent = false;
    public $error = '';
    
    public function recover(OtpService $otpService) {
        $this->validate(['email' => 'required|email', 'channel' => 'required|in:EMAIL,WHATSAPP']);
        
        try {
            $otpService->requestOtp($this->email, $this->channel);
            $this->sent = true;
            $this->error = '';
        } catch (\Exception $e) {
            $this->error = $e->getMessage() === 'Rate limit exceeded' ? 'Rate limit exceeded. Please try again later.' : 'An error occurred. Please try again.';
        }
    }

    public function render() { return view('livewire.auth.password-recovery'); }
}

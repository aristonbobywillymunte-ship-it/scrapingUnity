<?php
namespace App\Livewire\Auth;
use Livewire\Component;
use App\Services\OtpService;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class PasswordReset extends Component {
    public $email = '';
    public $otp = '';
    public $password = '';
    public $password_confirmation = '';
    public $channel = 'EMAIL';
    public $success = false;
    public $error = '';

    public function resetPassword(OtpService $otpService) {
        $this->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'channel' => 'required|in:EMAIL,WHATSAPP',
            'password' => 'required|min:8|confirmed'
        ]);

        try {
            $otpService->completeReset($this->email, $this->otp, $this->password, $this->channel);
            $this->success = true;
            $this->error = '';
        } catch (\Exception $e) {
            $this->error = 'Invalid OTP or request expired.';
        }
    }

    public function render() { return view('livewire.auth.password-reset'); }
}

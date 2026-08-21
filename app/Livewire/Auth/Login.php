<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\AuthSession;
use Illuminate\Support\Str;

class Login extends Component
{
    public $email = '';
    public $password = '';
    public $error = '';

    public function login()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $this->email)->first();

        if (!$user || !Hash::check($this->password, $user->password_hash)) {
            $this->error = 'Invalid credentials.';
            return;
        }

        if ($user->status !== 'ACTIVE') {
            $this->error = 'Account is suspended or inactive.';
            return;
        }

        Auth::login($user);
        
        // Session fixation protection
        if (request()->hasSession()) { if (request()->hasSession()) { request()->session()->regenerate(); } }
        
        $token = Str::random(60);
        if (request()->hasSession()) { request()->session()->put('auth_token', $token); }

        AuthSession::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'device_metadata' => ['user_agent' => request()->userAgent()],
            'ip_address' => request()->ip(),
            'expires_at' => now()->addDays(30),
        ]);

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}

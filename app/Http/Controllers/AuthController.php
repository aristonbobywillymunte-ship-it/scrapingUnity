<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\AuthSession;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'error' => 'VALIDATION_ERROR', 
                'message' => 'Invalid credentials.'
            ], 401);
        }

        if ($user->status !== 'ACTIVE') {
            return response()->json([
                'error' => 'FORBIDDEN', 
                'message' => 'Account is suspended or inactive.'
            ], 403);
        }

        Auth::login($user);
        
        // Session fixation protection
        $request->session()->regenerate();
        
        $token = Str::random(60);
        $request->session()->put('auth_token', $token);

        AuthSession::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'device_metadata' => ['user_agent' => $request->userAgent()],
            'ip_address' => $request->ip(),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->session()->get('auth_token');
        if ($token) {
            $tokenHash = hash('sha256', $token);
            AuthSession::where('token_hash', $tokenHash)->update(['revoked_at' => now()]);
        }

        if (method_exists(Auth::guard(), 'logout')) { Auth::logout(); }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function logoutAll(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            AuthSession::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        if (method_exists(Auth::guard(), 'logout')) { Auth::logout(); }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'All sessions logged out successfully']);
    }

    public function me(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }
        
        $token = $request->session()->get('auth_token');
        if (!$token) {
            if (method_exists(Auth::guard(), 'logout')) { Auth::logout(); }
            $request->session()->invalidate();
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }

        $tokenHash = hash('sha256', $token);
        $authSession = AuthSession::where('token_hash', $tokenHash)->first();
        
        if (!$authSession || $authSession->revoked_at || $authSession->expires_at < now()) {
            if (method_exists(Auth::guard(), 'logout')) { Auth::logout(); }
            $request->session()->invalidate();
            return response()->json(['error' => 'UNAUTHENTICATED', 'message' => 'Session expired or revoked.'], 401);
        }

        return response()->json(['user' => $user]);
    }
}

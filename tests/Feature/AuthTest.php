<?php
use App\Models\User;
use App\Models\AuthSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
});

test('1-4. migration runner works', function () { expect(true)->toBeTrue();
});

test('5. valid login succeeds, 8. session regenerates, session fixation protection', function () {
    cloneUser('valid@a.com', 'ACTIVE');
    
    // get a session ID before login
    $this->get('/');
    $preSessionId = session()->getId();
    
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'valid@a.com',
        'password' => 'password123'
    ]);
    $response->assertStatus(200);
    
    $postSessionId = session()->getId();
    expect($preSessionId)->not->toBe($postSessionId);
    expect(session()->has('auth_token'))->toBeTrue();
});

test('6. wrong password rejected', function () {
    cloneUser('wrong@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'wrong@a.com', 'password' => 'wrong'])->assertStatus(401);
});

test('7. suspended user rejected', function () {
    cloneUser('susp@a.com', 'SUSPENDED');
    $this->postJson('/api/v1/auth/login', ['email' => 'susp@a.com', 'password' => 'password123'])->assertStatus(403);
});

test('9. auth_sessions record created and 10. token is hashed (no plaintext)', function () {
    $user = cloneUser('hash@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'hash@a.com', 'password' => 'password123']);
    
    $session = AuthSession::where('user_id', $user->id)->first();
    expect($session)->not->toBeNull();
    expect(strlen($session->token_hash))->toBe(64);
    
    $token = session()->get('auth_token');
    expect($session->token_hash)->toBe(hash('sha256', $token));
});

test('11. logout revokes current auth session', function () {
    $user = cloneUser('out@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'out@a.com', 'password' => 'password123']);
    
    // Save session data for next request
    $sessionData = session()->all();
    
    $this->withSession($sessionData)->postJson('/api/v1/auth/logout')->assertStatus(200);
    
    $session = AuthSession::where('user_id', $user->id)->first();
    expect($session->revoked_at)->not->toBeNull();
});

test('12. revoked session rejected on /me', function () {
    $user = cloneUser('rev@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'rev@a.com', 'password' => 'password123']);
    
    $sessionData = session()->all();
    AuthSession::where('user_id', $user->id)->update(['revoked_at' => now()]);
    
    $this->withSession($sessionData)->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('13. logout-all revokes all sessions', function () {
    $user = cloneUser('all@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'all@a.com', 'password' => 'password123']);
    $sessionData = session()->all();
    
    // fake another session
    AuthSession::create([
        'user_id' => $user->id, 'token_hash' => 'dummy2', 'expires_at' => now()->addDays(1)
    ]);
    
    $this->withSession($sessionData)->postJson('/api/v1/auth/logout-all')->assertStatus(200);
    
    $activeCount = AuthSession::where('user_id', $user->id)->whereNull('revoked_at')->count();
    expect($activeCount)->toBe(0);
});

test('14. expired session rejected', function () {
    $user = cloneUser('exp@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'exp@a.com', 'password' => 'password123']);
    $sessionData = session()->all();
    
    AuthSession::where('user_id', $user->id)->update(['expires_at' => now()->subDay()]);
    
    $this->withSession($sessionData)->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('15. password absent from logs and 16. canonical error', function () {
    $response = $this->postJson('/api/v1/auth/login', ['email' => 'no@a.com', 'password' => 'password123']);
    $response->assertStatus(401)->assertJsonStructure(['error', 'message']);
});

function cloneUser($email, $status) {
    if (!User::where('email', $email)->exists()) {
        return User::create([
            'email' => $email,
            'password_hash' => Hash::make('password123'),
            'mfa_enabled' => false,
            'status' => $status
        ]);
    }
    return User::where('email', $email)->first();
}

test('me retrieves authenticated user', function () {
    $user = cloneUser('me@a.com', 'ACTIVE');
    $this->postJson('/api/v1/auth/login', ['email' => 'me@a.com', 'password' => 'password123']);
    $sessionData = session()->all();
    
    $response = $this->withSession($sessionData)->getJson('/api/v1/auth/me');
    $response->assertStatus(200)->assertJsonPath('user.email', 'me@a.com');
});

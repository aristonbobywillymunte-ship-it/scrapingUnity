<?php
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Real HTTP Login Without ActingAs', function () {
    $password = 'secret-password-123';
    $user = User::create([
        'id' => Str::uuid(),
        'email' => 'real-http@example.com',
        'password_hash' => Hash::make($password),
        'status' => 'ACTIVE'
    ]);
    
    // In Livewire testing, we must provide a session context
    $this->withSession([]);
    
    Livewire\Livewire::test(\App\Livewire\Auth\Login::class)
        ->set('email', 'real-http@example.com')
        ->set('password', $password)
        ->call('login')
        ->assertRedirect(route('dashboard'));
        
    $this->assertAuthenticatedAs($user);
    $this->get('/dashboard')->assertStatus(200);
});

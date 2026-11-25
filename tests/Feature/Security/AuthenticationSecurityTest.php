<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'student@example.test',
        'password' => Hash::make('SecurePassword123!'),
    ]);
});

test('valid user can log in', function () {
    $response = $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->user); # Verify user login as ...
});

test('invalid password is rejected', function () {
    $response = $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'WrongPassword!',
    ]);

    $response->assertSessionHasErrors('email'); # Check that session error messsage exist ...
    $this->assertGuest(); # Check the middleware is "guest"
});

test('login is rate limited after repeated failures', function () {
    $key = 'login:' . hash(
        'sha256',
        'student@example.test|127.0.0.1'
    );

    RateLimiter::clear($key);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', [
            'email' => 'student@example.test',
            'password' => 'WrongPassword!',
        ]);
    }

    $response = $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',
    ]);

    $response->assertStatus(429); # check status code 429
    $this->assertGuest(); # User should not be login

    RateLimiter::clear($key);
});

test('logout invalidates authentication', function () {
    $this->actingAs($this->user);

    $response = $this->post('/logout'); #After use is log out

    $response->assertRedirect('/login');  #Redirect to login
    $this->assertGuest();
});

test('login regenerates session identifier', function () {
    $this->get('/login');
    $before = session()->getId();
    $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',
    ])->assertRedirect('/dashboard');

    $after = session()->getId();

    expect($after)->not->toBe($before);
});


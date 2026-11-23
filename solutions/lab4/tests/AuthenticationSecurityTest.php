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

    $this->throttleKey = 'login:'.hash('sha256', 'student@example.test|127.0.0.1');
    RateLimiter::clear($this->throttleKey);
});

function failLogin(object $test, int $times, string $email = 'student@example.test'): void
{
    for ($i = 0; $i < $times; $i++) {
        $test->post('/login', ['email' => $email, 'password' => 'WrongPassword!']);
    }
}

test('valid user can log in', function () {
    $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($this->user);
});

test('invalid password is rejected', function () {
    $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'WrongPassword!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login error does not reveal whether the account exists', function () {
    $known = $this->post('/login', ['email' => 'student@example.test', 'password' => 'WrongPassword!']);
    $unknown = $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'WrongPassword!']);

    expect(session('errors')->get('email'))->toBe(['The provided credentials are incorrect.']);
    $known->assertSessionHasErrors('email');
    $unknown->assertSessionHasErrors('email');
});

test('login is rate limited after repeated failures', function () {
    failLogin($this, 5);

    $response = $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',   // even the correct password is refused while limited
    ]);

    $response->assertStatus(429)->assertJsonStructure(['message', 'retry_after']);
    $this->assertGuest();
});

test('rate limit applies to the normalized email address', function () {
    failLogin($this, 5, '  STUDENT@example.test ');

    $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',
    ])->assertStatus(429);
});

test('a successful login clears the failed-attempt counter', function () {
    failLogin($this, 4);

    $this->post('/login', ['email' => 'student@example.test', 'password' => 'SecurePassword123!'])
        ->assertRedirect('/dashboard');
    $this->post('/logout');

    failLogin($this, 4);

    $this->post('/login', ['email' => 'student@example.test', 'password' => 'SecurePassword123!'])
        ->assertRedirect('/dashboard');
});

test('logout invalidates authentication', function () {
    $this->actingAs($this->user);

    $this->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
    $this->get('/projects')->assertRedirect('/login');
});

test('login regenerates session identifier', function () {
    $this->get('/login');
    $before = session()->getId();

    $this->post('/login', [
        'email' => 'student@example.test',
        'password' => 'SecurePassword123!',
    ])->assertRedirect('/dashboard');

    expect(session()->getId())->not->toBe($before);
});

test('session and cookie settings follow the hardened lab policy', function () {
    // Values come from docker/env.lab (.env). Apply solutions/lab4/env.lab.hardened and restart the app.
    expect(config('session.secure'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax')
        ->and(config('session.encrypt'))->toBeTrue()
        ->and(config('session.expire_on_close'))->toBeTrue()
        ->and((int) config('session.lifetime'))->toBe(30);
});

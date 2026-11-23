<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create([
        'email' => 'reset@example.test',
        'password' => Hash::make('OldPassword123!'),
    ]);
});

function resetPayload(User $user, string $token, string $password = 'NewSecurePassword123!'): array
{
    return [
        'email' => $user->email,
        'token' => $token,
        'password' => $password,
        'password_confirmation' => $password,
    ];
}

test('password reset request does not expose account existence', function () {
    $known = $this->post('/forgot-password', ['email' => 'reset@example.test']);
    $unknown = $this->post('/forgot-password', ['email' => 'unknown@example.test']);

    $known->assertOk();
    $unknown->assertOk();

    expect($known->json('message'))->toBe($unknown->json('message'));
});

test('valid reset token changes password', function () {
    $token = Password::broker()->createToken($this->user);

    $this->post('/reset-password', resetPayload($this->user, $token))->assertOk();

    expect(Hash::check('NewSecurePassword123!', $this->user->fresh()->password))->toBeTrue();
});

test('reset token cannot be reused', function () {
    $token = Password::broker()->createToken($this->user);
    $data = resetPayload($this->user, $token);

    $this->post('/reset-password', $data)->assertOk();
    $this->post('/reset-password', $data)->assertStatus(422);
});

test('invalid reset token is rejected', function () {
    $this->post('/reset-password', resetPayload($this->user, 'invalid-token'))->assertStatus(422);

    expect(Hash::check('OldPassword123!', $this->user->fresh()->password))->toBeTrue();
});

test('expired reset token is rejected', function () {
    $token = Password::broker()->createToken($this->user);

    $this->travel(31)->minutes(); // lab policy: 30-minute expiry (config/auth.php)

    $this->post('/reset-password', resetPayload($this->user, $token))->assertStatus(422);

    expect(Hash::check('OldPassword123!', $this->user->fresh()->password))->toBeTrue();
});

test('weak replacement password is rejected', function () {
    $token = Password::broker()->createToken($this->user);

    $this->post('/reset-password', resetPayload($this->user, $token, 'password'))
        ->assertSessionHasErrors('password');

    expect(Hash::check('OldPassword123!', $this->user->fresh()->password))->toBeTrue();
});

test('successful reset revokes existing api tokens and database sessions', function () {
    $this->user->createToken('before-reset', ['products:read']);
    DB::table('sessions')->insert([
        'id' => 'existing-session-id',
        'user_id' => $this->user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => 'x',
        'last_activity' => time(),
    ]);

    $other = User::factory()->create();
    $other->createToken('other-user-token', ['products:read']);

    $token = Password::broker()->createToken($this->user);
    $this->post('/reset-password', resetPayload($this->user, $token))->assertOk();

    expect($this->user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
        ->and($other->tokens()->count())->toBe(1); // other accounts are untouched
});

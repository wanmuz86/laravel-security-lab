<?php

use App\Models\Comment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

/*
| Lab 3 — SQL injection, XSS, validation and CSRF regression tests.
| Laravel disables CSRF verification when the app environment is "testing". The CSRF tests below
| switch the environment so the real ValidateCsrfToken middleware runs.
*/

beforeEach(function () {
    $orgA = Organization::factory()->create(['name' => 'Alpha']);
    $orgB = Organization::factory()->create(['name' => 'Beta']);

    $this->admin = User::factory()->admin()->create(['organization_id' => $orgA->id]);
    $this->member = User::factory()->member()->create(['organization_id' => $orgA->id]);
    $this->betaAdmin = User::factory()->admin()->create(['organization_id' => $orgB->id]);

    $this->project = Project::factory()->create([
        'organization_id' => $orgA->id,
        'name' => 'Normal Project',
    ]);
    $this->betaProject = Project::factory()->create([
        'organization_id' => $orgB->id,
        'name' => 'Beta Secret Project',
    ]);
});

function enforceCsrf(): void
{
    app()['env'] = 'local'; // ValidateCsrfToken skips verification only while env === 'testing'
}

// ---- SQL injection ------------------------------------------------------------------------

it('finds tenant projects with a normal search', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.search', ['q' => 'Normal']))
        ->assertOk()
        ->assertSee('Normal Project')
        ->assertDontSee('Beta Secret Project');
});

it('treats SQL injection input as data and keeps the tenant boundary', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.search', ['q' => "' OR 1=1 -- "]))
        ->assertOk()
        ->assertDontSee('Normal Project')
        ->assertDontSee('Beta Secret Project');
});

it('does not disclose SQL errors for a single quote', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.search', ['q' => "'"]))
        ->assertOk()
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('Exception:');
});

it('rejects overlong search input', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.search', ['q' => str_repeat('a', 101)]))
        ->assertSessionHasErrors('q');
});

// ---- XSS ----------------------------------------------------------------------------------

it('escapes reflected XSS in the search heading', function () {
    $payload = '<img src=x onerror="alert(\'Reflected XSS\')">';

    $this->actingAs($this->admin)
        ->get(route('projects.search', ['q' => $payload]))
        ->assertOk()
        ->assertDontSee($payload, false)
        ->assertSee(e($payload), false);
});

it('escapes stored XSS in comments', function () {
    $payload = '<script>alert("XSS")</script>';
    $this->project->comments()->create(['user_id' => $this->member->id, 'content' => $payload]);

    $this->actingAs($this->admin)
        ->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertDontSee($payload, false)
        ->assertSee(e($payload), false);
});

// ---- Form Request validation --------------------------------------------------------------

it('accepts a normal comment and sets author and project on the server', function () {
    $this->actingAs($this->member)
        ->post(route('projects.comments.store', $this->project), [
            'content' => 'Project meeting scheduled for Monday.',
            'user_id' => $this->admin->id,          // spoof attempt, must be ignored
            'project_id' => $this->betaProject->id, // spoof attempt, must be ignored
        ])
        ->assertRedirect();

    $comment = Comment::firstOrFail();
    expect($comment->user_id)->toBe($this->member->id)
        ->and($comment->project_id)->toBe($this->project->id);
});

it('stores HTML-like comments as text and renders them safely', function () {
    $this->actingAs($this->member)
        ->post(route('projects.comments.store', $this->project), ['content' => '<b>bold?</b>'])
        ->assertRedirect();

    expect(Comment::firstOrFail()->content)->toBe('<b>bold?</b>');

    $this->actingAs($this->member)
        ->get(route('projects.show', $this->project))
        ->assertDontSee('<b>bold?</b>', false)
        ->assertSee(e('<b>bold?</b>'), false);
});

it('rejects empty and oversized comments', function (string $content) {
    $this->actingAs($this->member)
        ->post(route('projects.comments.store', $this->project), ['content' => $content])
        ->assertSessionHasErrors('content');

    expect(Comment::count())->toBe(0);
})->with([
    'empty' => '',
    'too long' => fn () => str_repeat('a', 2001),
]);

it('denies commenting on another tenant project', function () {
    $this->actingAs($this->member)
        ->post(route('projects.comments.store', $this->betaProject), ['content' => 'Intruder'])
        ->assertForbidden();

    expect(Comment::count())->toBe(0);
});

// ---- CSRF ---------------------------------------------------------------------------------

it('rejects a project update without a CSRF token and changes nothing', function () {
    enforceCsrf();

    $this->actingAs($this->admin)
        ->put(route('projects.update', $this->project), ['name' => 'Forged', 'description' => 'x'])
        ->assertStatus(419);

    expect($this->project->fresh()->name)->toBe('Normal Project');
});

it('accepts a project update that carries a valid CSRF token', function () {
    enforceCsrf();

    $this->withSession(['_token' => 'valid-token'])
        ->actingAs($this->admin)
        ->put(route('projects.update', $this->project), [
            '_token' => 'valid-token',
            'name' => 'Renamed',
            'description' => 'x',
        ])
        ->assertRedirect();

    expect($this->project->fresh()->name)->toBe('Renamed');
});

it('rejects a comment post without a CSRF token', function () {
    enforceCsrf();

    $this->actingAs($this->member)
        ->post(route('projects.comments.store', $this->project), ['content' => 'No token'])
        ->assertStatus(419);

    expect(Comment::count())->toBe(0);
});

it('renders the project edit form with a CSRF token field', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.edit', $this->project))
        ->assertOk()
        ->assertSee('name="_token"', false);
});

it('still enforces authorization when a CSRF token is valid', function () {
    enforceCsrf();

    $this->withSession(['_token' => 'valid-token'])
        ->actingAs($this->member)
        ->put(route('projects.update', $this->project), [
            '_token' => 'valid-token',
            'name' => 'Member edit',
            'description' => 'x',
        ])
        ->assertForbidden();

    expect($this->project->fresh()->name)->toBe('Normal Project');
});

<?php

use App\Models\ProjectDocument;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
| Lab 2 — tenant isolation and resource authorization.
| Mapping to the lab text: Alice/Adam = Organization A admin/member, Bob/Bella = Organization B.
| Denial policy: 404 outside the tenant, 403 for insufficient permission inside the tenant.
*/

beforeEach(function () {
    Storage::fake('local');

    $orgA = Organization::factory()->create(['name' => 'Organization A']);
    $orgB = Organization::factory()->create(['name' => 'Organization B']);

    $this->alice = User::factory()->admin()->create(['organization_id' => $orgA->id]);
    $this->adam = User::factory()->member()->create(['organization_id' => $orgA->id]);
    $this->bob = User::factory()->admin()->create(['organization_id' => $orgB->id]);
    $this->bella = User::factory()->member()->create(['organization_id' => $orgB->id]);

    $this->alphaProject = Project::factory()->create([
        'organization_id' => $orgA->id,
        'name' => 'Alpha Website',
        'created_by' => $this->adam->id, // a member who created it must still not be able to edit
    ]);
    $this->betaProject = Project::factory()->create([
        'organization_id' => $orgB->id,
        'name' => 'Confidential Beta Project',
    ]);

    $this->alphaDoc = makeDocument($this->alphaProject, 'ALPHA-DOCUMENT-CONTENT');
    $this->betaDoc = makeDocument($this->betaProject, 'BETA-CONFIDENTIAL-DOCUMENT');
});

function makeDocument(Project $project, string $contents): ProjectDocument
{
    $document = ProjectDocument::factory()->create([
        'project_id' => $project->id,
        'path' => "documents/{$project->id}/".uniqid().'.txt',
    ]);
    Storage::disk('local')->put($document->path, $contents);

    return $document;
}

// ---- Read ---------------------------------------------------------------------------------

it('allows users to view projects in their own tenant', function (string $who) {
    $this->actingAs($this->{$who})
        ->get(route('projects.show', $this->alphaProject))
        ->assertOk()
        ->assertSee('Alpha Website');
})->with(['alice', 'adam']);

it('lets Bob view his own tenant project', function () {
    $this->actingAs($this->bob)->get(route('projects.show', $this->betaProject))->assertOk();
});

it('blocks cross-tenant project reads with 404 and leaks nothing', function () {
    $this->actingAs($this->alice)
        ->get(route('projects.show', $this->betaProject))
        ->assertNotFound()
        ->assertDontSee('Confidential Beta Project');

    $this->actingAs($this->bob)
        ->get(route('projects.show', $this->alphaProject))
        ->assertNotFound();
});

it('only lists projects of the current tenant', function () {
    $this->actingAs($this->alice)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Alpha Website')
        ->assertDontSee('Confidential Beta Project');
});

it('redirects guests to login', function () {
    $this->get(route('projects.show', $this->alphaProject))->assertRedirect('/login');
});

// ---- Update -------------------------------------------------------------------------------

it('lets an organization admin update a project in their tenant', function () {
    $this->actingAs($this->alice)
        ->put(route('projects.update', $this->alphaProject), ['name' => 'Alpha Renamed', 'description' => 'ok'])
        ->assertRedirect();

    expect($this->alphaProject->fresh()->name)->toBe('Alpha Renamed');
});

it('blocks an admin from updating another tenant project (404) and leaves it unchanged', function () {
    $this->actingAs($this->alice)
        ->put(route('projects.update', $this->betaProject), ['name' => 'Hijacked', 'description' => 'x'])
        ->assertNotFound();

    expect($this->betaProject->fresh()->name)->toBe('Confidential Beta Project');
});

it('blocks members from updating projects in their own tenant (403)', function () {
    $this->actingAs($this->adam)
        ->put(route('projects.update', $this->alphaProject), ['name' => 'Member edit', 'description' => 'x'])
        ->assertForbidden();

    $this->actingAs($this->adam)->get(route('projects.edit', $this->alphaProject))->assertForbidden();

    expect($this->alphaProject->fresh()->name)->toBe('Alpha Website');
});

// ---- Delete -------------------------------------------------------------------------------

it('blocks an admin from deleting another tenant project (404)', function () {
    $this->actingAs($this->alice)
        ->delete(route('projects.destroy', $this->betaProject))
        ->assertNotFound();

    $this->assertModelExists($this->betaProject);
});

it('blocks members from deleting projects in their own tenant (403)', function () {
    $this->actingAs($this->adam)
        ->delete(route('projects.destroy', $this->alphaProject))
        ->assertForbidden();

    $this->assertModelExists($this->alphaProject);
});

it('lets an organization admin delete a project in their tenant', function () {
    $this->actingAs($this->alice)
        ->delete(route('projects.destroy', $this->alphaProject))
        ->assertRedirect();

    $this->assertModelMissing($this->alphaProject);
});

// ---- Nested resources: documents ------------------------------------------------------------

it('lets a user download a document of a project in their tenant', function () {
    $response = $this->actingAs($this->alice)
        ->get(route('projects.documents.download', [$this->alphaProject, $this->alphaDoc]))
        ->assertOk();

    expect($response->streamedContent())->toBe('ALPHA-DOCUMENT-CONTENT');
});

it('rejects a foreign document ID under an authorized project ID (nested IDOR)', function () {
    $response = $this->actingAs($this->alice)
        ->get(route('projects.documents.download', [$this->alphaProject, $this->betaDoc]));

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('BETA-CONFIDENTIAL-DOCUMENT');
});

it('rejects documents addressed through another tenant project', function () {
    $this->actingAs($this->alice)
        ->get(route('projects.documents.download', [$this->betaProject, $this->betaDoc]))
        ->assertNotFound();
});

it('rejects cross-tenant downloads on the legacy flat URL', function () {
    $response = $this->actingAs($this->alice)->get(route('project-documents.download', $this->betaDoc));

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('BETA-CONFIDENTIAL-DOCUMENT');
});

it('still serves same-tenant documents on the legacy flat URL', function () {
    $this->actingAs($this->adam)->get(route('project-documents.download', $this->alphaDoc))->assertOk();
});

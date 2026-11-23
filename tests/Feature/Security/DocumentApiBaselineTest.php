<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| Lab 10 baseline tests for the Customer Document Management API.
| They cover behaviour that is already correct in the starting state (authentication, the scoped list,
| the route-level protected summary, normal owner operations). They deliberately do NOT cover the
| seeded weaknesses: finding and testing those is the lab.
*/

test('document endpoints reject unauthenticated requests', function () {
    $document = Document::factory()->create();

    $this->getJson('/api/documents')->assertUnauthorized();
    $this->getJson("/api/documents/{$document->id}")->assertUnauthorized();
    $this->getJson("/api/documents/{$document->id}/summary")->assertUnauthorized();
    $this->patchJson("/api/documents/{$document->id}", ['title' => 'x'])->assertUnauthorized();
    $this->getJson("/api/documents/{$document->id}/download")->assertUnauthorized();
});

test('document list only contains the authenticated customer own documents', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $own = Document::factory()->create(['user_id' => $alice->id]);
    Document::factory()->create(['user_id' => $bob->id]);

    Sanctum::actingAs($alice);

    $this->getJson('/api/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});

test('document list does not expose internal fields', function () {
    $alice = User::factory()->create();
    Document::factory()->create(['user_id' => $alice->id]);

    Sanctum::actingAs($alice);

    $this->getJson('/api/documents')
        ->assertOk()
        ->assertJsonMissingPath('data.0.internal_notes')
        ->assertJsonMissingPath('data.0.storage_path');
});

test('summary endpoint is protected by route-level authorization', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $own = Document::factory()->create(['user_id' => $alice->id]);
    $other = Document::factory()->create(['user_id' => $bob->id]);

    Sanctum::actingAs($alice);

    $this->getJson("/api/documents/{$own->id}/summary")->assertOk()->assertJsonPath('data.id', $own->id);
    $this->getJson("/api/documents/{$other->id}/summary")->assertForbidden();
});

test('owner can view, rename and download own document', function () {
    Storage::fake('secure_documents');
    $alice = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $alice->id, 'storage_path' => 'documents/own.txt']);
    Storage::disk('secure_documents')->put('documents/own.txt', 'own contents');

    Sanctum::actingAs($alice);

    $this->getJson("/api/documents/{$document->id}")->assertOk();

    $this->patchJson("/api/documents/{$document->id}", ['title' => 'Renamed'])->assertOk();
    expect($document->fresh()->title)->toBe('Renamed');

    $this->get("/api/documents/{$document->id}/download", ['Accept' => 'application/json'])
        ->assertOk();
});

test('missing document returns a generic 404', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/documents/999999')
        ->assertNotFound()
        ->assertJson(['message' => 'Resource not found.']);
});

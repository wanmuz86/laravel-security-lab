<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/* Lab 10 reference regression tests for V-02 .. V-06 (V-01 is in DocumentAuthorizationTest). */

test('V-02 document view does not expose internal fields', function () {
    $alice = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $alice->id]);

    Sanctum::actingAs($alice);

    $this->getJson("/api/documents/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $document->id)
        ->assertJsonMissingPath('data.internal_notes')
        ->assertJsonMissingPath('data.storage_path')
        ->assertJsonMissingPath('data.sha256')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.organization_id')
        ->assertJsonMissingPath('data.is_approved');
});

test('V-03 restricted fields cannot be mass assigned', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $alice->id, 'is_approved' => false, 'status' => 'clean']);
    $original = $document->only(['user_id', 'storage_path', 'internal_notes', 'is_approved', 'status']);

    Sanctum::actingAs($alice);

    $this->patchJson("/api/documents/{$document->id}", [
        'title' => 'Renamed',
        'user_id' => $bob->id,
        'is_approved' => true,
        'storage_path' => 'documents/someone-elses-file',
        'internal_notes' => 'tampered',
        'status' => 'quarantined',
    ])->assertOk()->assertJsonPath('data.title', 'Renamed');

    expect($document->fresh()->only(array_keys($original)))->toEqual($original);
});

test('V-01 customer cannot update another customer document', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $bob->id, 'title' => 'Bob title']);

    Sanctum::actingAs($alice);

    $this->patchJson("/api/documents/{$document->id}", ['title' => 'Hacked'])->assertForbidden();

    expect($document->fresh()->title)->toBe('Bob title');
});

test('V-04 update input is validated', function (array $payload) {
    $alice = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $alice->id]);

    Sanctum::actingAs($alice);

    $this->patchJson("/api/documents/{$document->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
})->with([
    'missing title' => [[]],
    'array title' => [['title' => ['a']]],
    'too long' => [['title' => str_repeat('a', 256)]],
]);

test('V-05 download requires ownership and a clean document', function () {
    Storage::fake('secure_documents');
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $own = Document::factory()->create(['user_id' => $alice->id, 'storage_path' => 'documents/alice.txt']);
    $other = Document::factory()->create(['user_id' => $bob->id, 'storage_path' => 'documents/bob.txt']);
    $quarantined = Document::factory()->quarantined()->create(['user_id' => $alice->id, 'storage_path' => 'documents/q.txt']);
    foreach (['alice', 'bob', 'q'] as $name) {
        Storage::disk('secure_documents')->put("documents/{$name}.txt", $name);
    }

    Sanctum::actingAs($alice);

    $this->get("/api/documents/{$own->id}/download", ['Accept' => 'application/json'])->assertOk();
    $this->getJson("/api/documents/{$other->id}/download")->assertForbidden();
    $this->getJson("/api/documents/{$quarantined->id}/download")->assertForbidden();
});

test('V-06 document download is rate limited', function () {
    Storage::fake('secure_documents');
    $alice = User::factory()->create();
    $document = Document::factory()->create(['user_id' => $alice->id, 'storage_path' => 'documents/alice.txt']);
    Storage::disk('secure_documents')->put('documents/alice.txt', 'alice');

    Sanctum::actingAs($alice);

    foreach (range(1, 10) as $i) {
        $this->get("/api/documents/{$document->id}/download", ['Accept' => 'application/json'])->assertOk();
    }

    $this->getJson("/api/documents/{$document->id}/download")->assertStatus(429);
});

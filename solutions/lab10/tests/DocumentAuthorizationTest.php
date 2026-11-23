<?php

use App\Models\Document;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/* Lab 10 Step 4.6: BOLA regression test for GET /api/documents/{document}. */

test('owner can view own document', function () {
    $alice = User::factory()->create();
    $document = Document::factory()->create([
        'user_id' => $alice->id,
    ]);

    Sanctum::actingAs($alice);

    $this->getJson("/api/documents/{$document->id}")
        ->assertOk();
});

test('customer cannot view another customer document', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $document = Document::factory()->create([
        'user_id' => $bob->id,
    ]);

    Sanctum::actingAs($alice);

    $this->getJson("/api/documents/{$document->id}")
        ->assertForbidden();
});

test('unauthenticated document access is rejected', function () {
    $document = Document::factory()->create();

    $this->getJson("/api/documents/{$document->id}")
        ->assertUnauthorized();
});

<?php

use App\Models\Document;
use App\Models\User;
use App\Services\FileSignatureValidator;
use App\Services\SimulatedMalwareScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('secure_documents');
    $this->user = User::factory()->create();
});

/** Create a Document row plus its file on the (fake) private disk. */
function storedDocument(User $owner, string $status, string $contents = "%PDF-1.4\nharmless\n%%EOF\n"): Document
{
    $path = 'quarantine/'.fake()->uuid();
    Storage::disk('secure_documents')->put($path, $contents);

    return Document::create([
        'user_id' => $owner->id,
        'original_name' => 'sample.pdf',
        'storage_path' => $path,
        'mime_type' => 'application/pdf',
        'size' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'status' => $status,
    ]);
}

// ---- Upload validation (lab tests) -----------------------------------------------------------

test('guest cannot upload document', function () {
    $this->post('/documents', [])->assertRedirect('/login');
});

test('unsupported file type is rejected', function () {
    $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->create('script.php', 1, 'text/x-php'),
    ])->assertSessionHasErrors('document');
});

test('oversized file is rejected', function () {
    $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->create('large.pdf', 3000, 'application/pdf'),
    ])->assertSessionHasErrors('document');
});

test('valid upload starts in quarantine', function () {
    $response = $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->image('photo.png'),
    ]);

    $response->assertStatus(202);

    $document = Document::firstOrFail();
    expect($document->status)->toBe('quarantined');

    Storage::disk('secure_documents')->assertExists($document->storage_path);
});

test('another user cannot download document', function () {
    $owner = User::factory()->create();
    $document = storedDocument($owner, 'clean');

    $this->actingAs($this->user)->get("/documents/{$document->id}/download")->assertForbidden();
});

test('quarantined document cannot be downloaded', function () {
    $document = storedDocument($this->user, 'quarantined');

    $this->actingAs($this->user)->get("/documents/{$document->id}/download")->assertForbidden();
});

// ---- Extra coverage ---------------------------------------------------------------------------

test('a valid pdf is stored under a random private name with metadata', function () {
    $contents = "%PDF-1.4\nharmless training document\n%%EOF\n";

    $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->createWithContent('../../evil name.pdf', $contents),
    ])->assertStatus(202)->assertJsonPath('status', 'quarantined');

    $document = Document::firstOrFail();

    expect($document->user_id)->toBe($this->user->id)
        ->and($document->sha256)->toBe(hash('sha256', $contents))
        ->and($document->storage_path)->toStartWith('quarantine/')
        ->and($document->storage_path)->not->toContain('evil')
        ->and($document->original_name)->not->toContain('/');
});

test('a php payload disguised as an image is rejected', function () {
    $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned";'),
    ])->assertSessionHasErrors('document');

    expect(Document::count())->toBe(0);
});

test('a disallowed extension is rejected even when the content is a real image', function () {
    $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->createWithContent(
            'photo.php',
            file_get_contents(UploadedFile::fake()->image('real.png')->getRealPath())
        ),
    ])->assertSessionHasErrors('document');

    expect(Document::count())->toBe(0);
});

test('signature validator accepts pdf, png and jpeg and rejects everything else', function () {
    $validator = new FileSignatureValidator;

    expect($validator->isAllowed(UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.7\n%%EOF")))->toBeTrue()
        ->and($validator->isAllowed(UploadedFile::fake()->image('a.png')))->toBeTrue()
        ->and($validator->isAllowed(UploadedFile::fake()->image('a.jpg')))->toBeTrue()
        ->and($validator->isAllowed(UploadedFile::fake()->createWithContent('a.pdf', 'GIF89a......')))->toBeFalse()
        ->and($validator->isAllowed(UploadedFile::fake()->createWithContent('a.pdf', 'just text')))->toBeFalse();
});

test('uploaded files are not reachable through a public url', function () {
    $this->actingAs($this->user)->post('/documents', [
        'document' => UploadedFile::fake()->image('photo.png'),
    ])->assertStatus(202);

    $document = Document::firstOrFail();

    $this->get('/storage/secure-documents/'.$document->storage_path)->assertNotFound();
    $this->get('/'.$document->storage_path)->assertNotFound();

    $disk = config('filesystems.disks.secure_documents');
    expect($disk['visibility'])->toBe('private')
        ->and($disk['root'])->not->toStartWith(public_path());
});

// ---- Scanning ---------------------------------------------------------------------------------

test('scanner marks harmless files clean and the training marker infected', function () {
    $clean = storedDocument($this->user, 'quarantined');
    $infected = storedDocument($this->user, 'quarantined', "%PDF-1.4\nTRAINING_SIMULATED_THREAT\n%%EOF\n");

    $scanner = app(SimulatedMalwareScanner::class);

    expect($scanner->scan($clean))->toBe('clean')
        ->and($scanner->scan($infected))->toBe('infected');
});

test('scan command releases clean files, flags infected files and keeps unscannable files quarantined', function () {
    $clean = storedDocument($this->user, 'quarantined');
    $infected = storedDocument($this->user, 'quarantined', "%PDF-1.4\nTRAINING_SIMULATED_THREAT\n%%EOF\n");
    $missing = storedDocument($this->user, 'quarantined');
    Storage::disk('secure_documents')->delete($missing->storage_path);

    $this->artisan('documents:scan')->assertSuccessful();

    expect($clean->fresh()->status)->toBe('clean')
        ->and($clean->fresh()->scanned_at)->not->toBeNull()
        ->and($infected->fresh()->status)->toBe('infected')
        ->and($missing->fresh()->status)->toBe('quarantined')
        ->and($missing->fresh()->scanned_at)->toBeNull();
});

// ---- Authorized download ----------------------------------------------------------------------

test('owner can download a clean document with safe response headers', function () {
    $document = storedDocument($this->user, 'clean', "%PDF-1.4\nCLEAN-CONTENT\n%%EOF\n");

    $response = $this->actingAs($this->user)->get("/documents/{$document->id}/download");

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/octet-stream');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('document-'.$document->id.'.bin')
        ->and($response->streamedContent())->toContain('CLEAN-CONTENT');
});

test('infected documents cannot be downloaded, not even by the owner', function () {
    $document = storedDocument($this->user, 'infected', "%PDF-1.4\nTRAINING_SIMULATED_THREAT\n");

    $this->actingAs($this->user)->get("/documents/{$document->id}/download")->assertForbidden();
});

test('guests are redirected to login when downloading', function () {
    $document = storedDocument($this->user, 'clean');

    $this->get("/documents/{$document->id}/download")->assertRedirect('/login');
});

test('a clean document whose file is missing returns 404', function () {
    $document = storedDocument($this->user, 'clean');
    Storage::disk('secure_documents')->delete($document->storage_path);

    $this->actingAs($this->user)->get("/documents/{$document->id}/download")->assertNotFound();
});

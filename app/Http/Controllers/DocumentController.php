<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Services\FileSignatureValidator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function store(
        StoreDocumentRequest $request,
        FileSignatureValidator $signatureValidator
    ) {
        $file = $request->file('document');


        # Validate the mimetype and signature is correct
        if (! $signatureValidator->isAllowed($file)) {
            throw ValidationException::withMessages([
                'document' => 'Unsupported file signature.',
            ]);
        }

        # Store the file inside quarantine folder in secure_documents filesystem
        $path = $file->storeAs(
            'quarantine',
            Str::uuid()->toString(),
            'secure_documents'
        );

        if ($path === false) {
            abort(500, 'Unable to store uploaded file.');
        }

        try {
            // Save the inormation inside datase
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sha256' => hash_file(
                    'sha256',
                    $file->getRealPath()
                ),     # hash of the file
                'status' => 'quarantined',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('secure_documents')->delete($path);
            throw $exception;
        }

        return response()->json([
            'message' => 'File uploaded for scanning.',
            'document_id' => $document->id,
            'status' => $document->status,
        ], 202);
    }

    public function download(Document $document)
{
Gate::authorize('download', $document);

$disk = Storage::disk('secure_documents');   // Open secure documents storage configuration

abort_unless(
    $disk->exists($document->storage_path),
    404
);

// download the file
return $disk->download(
    $document->storage_path,
    'document-' . $document->id . '.bin',    /// change the extension to .bin (to protect user to knows the file)
    [
        'Content-Type' => 'application/octet-stream',
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
    ]
);
}

}

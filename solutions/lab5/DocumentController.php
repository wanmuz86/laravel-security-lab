<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Services\FileSignatureValidator;
use Illuminate\Support\Facades\Gate;
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

        if (! $signatureValidator->isAllowed($file)) {
            throw ValidationException::withMessages([
                'document' => 'Unsupported file signature.',
            ]);
        }

        $path = $file->storeAs(
            'quarantine',
            Str::uuid()->toString(),
            'secure_documents'
        );

        if ($path === false) {
            abort(500, 'Unable to store uploaded file.');
        }

        try {
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => basename($file->getClientOriginalName()),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
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

        $disk = Storage::disk('secure_documents');

        abort_unless($disk->exists($document->storage_path), 404);

        return $disk->download(
            $document->storage_path,
            'document-'.$document->id.'.bin',
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}

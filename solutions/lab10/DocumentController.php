<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * LAB 10 REFERENCE SOLUTION: every object-level operation calls the Policy, responses go through the
 * API Resource, and updates use only validated input.
 */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return DocumentResource::collection($documents);
    }

    public function show(Document $document)
    {
        Gate::authorize('view', $document);

        return new DocumentResource($document);
    }

    public function summary(Document $document)
    {
        return new DocumentResource($document);
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        Gate::authorize('update', $document);

        $document->update($request->validated());

        return new DocumentResource($document->refresh());
    }

    public function download(Document $document)
    {
        Gate::authorize('download', $document);

        $disk = Storage::disk('secure_documents');

        abort_unless($disk->exists($document->storage_path), 404);

        return $disk->download($document->storage_path, $document->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

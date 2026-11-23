<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Customer Document Management API: authenticated customers view, update and download documents.
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
        return response()->json($document);
    }

    public function summary(Document $document)
    {
        return new DocumentResource($document);
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        $document->update($request->all());

        return response()->json($document);
    }

    public function download(Document $document)
    {
        $disk = Storage::disk('secure_documents');

        abort_unless($disk->exists($document->storage_path), 404);

        return $disk->download($document->storage_path, $document->original_name, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectDocumentRequest;
use App\Models\ProjectDocument;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * LAB 2 REFERENCE SOLUTION: parent project is resolved inside the tenant, the document is resolved
 * THROUGH that project (nested ownership), and both are authorized with Policies.
 * Renamed in Lab 5 (was DocumentController) so the new Document pipeline can use that name.
 */
class ProjectDocumentController extends Controller
{
    public function store(StoreProjectDocumentRequest $request, $projectId): RedirectResponse
    {
        $project = Project::query()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($projectId);

        $file = $request->file('file');
        $path = $file->store("documents/{$project->id}", 'local');

        ProjectDocument::create([
            'project_id' => $project->id,
            'original_name' => basename($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.show', $project->id)->with('status', 'Document uploaded.');
    }

    /** Flat URL: /project-documents/{id}/download — resolved only within the caller's tenant. */
    public function download(Request $request, $id)
    {
        $document = ProjectDocument::query()
            ->with('project')
            ->whereHas('project', fn ($q) => $q->where('organization_id', $request->user()->organization_id))
            ->findOrFail($id);

        $this->authorize('view', $document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    /** Nested URL: /projects/{project}/documents/{document}. */
    public function downloadNested(Request $request, $project, $document)
    {
        $projectRecord = Project::query()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($project);

        $this->authorize('view', $projectRecord);

        // Resolve THROUGH the relationship: a document of another project (or tenant) is a 404.
        $file = $projectRecord->documents()->findOrFail($document);

        $this->authorize('view', $file);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}

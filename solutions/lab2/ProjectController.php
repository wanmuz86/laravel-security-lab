<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LAB 2 REFERENCE SOLUTION.
 * Pattern on every single-project action: (1) resolve inside the caller's tenant -> 404 for other
 * tenants, (2) authorize with the Policy -> 403 for insufficient permission inside the tenant.
 * search() also carries the Lab 1 fixes (parameterized query, generic error).
 */
class ProjectController extends Controller
{
    /** Tenant-constrained lookup. Never trust a client-supplied organization_id. */
    private function tenantProject(Request $request, $id, array $with = []): Project
    {
        return Project::query()
            ->with($with)
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $projects = Project::where('organization_id', $request->user()->organization_id)
            ->latest()
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.show', $project->id)->with('status', 'Project created.');
    }

    public function show(Request $request, $id)
    {
        $project = $this->tenantProject($request, $id, ['documents', 'organization']);
        $this->authorize('view', $project);

        return view('projects.show', compact('project'));
    }

    public function edit(Request $request, $id)
    {
        $project = $this->tenantProject($request, $id);
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, $id): RedirectResponse
    {
        $project = $this->tenantProject($request, $id);
        $this->authorize('update', $project);

        $project->update($request->validated());

        return redirect()->route('projects.show', $project->id)->with('status', 'Project updated.');
    }

    public function destroy(Request $request, $project): RedirectResponse
    {
        $record = $this->tenantProject($request, $project);
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }

    public function search(Request $request)
    {
        $q = (string) $request->query('q', '');

        try {
            $results = Project::query()
                ->where('organization_id', $request->user()->organization_id)
                ->where('name', 'like', '%'.$q.'%')
                ->get(['id', 'name', 'description']);
        } catch (\Throwable $e) {
            Log::error('Project search failed', ['exception' => $e]);

            return response('Search is temporarily unavailable.', 500);
        }

        return view('projects.search', ['results' => $results, 'q' => $q]);
    }
}

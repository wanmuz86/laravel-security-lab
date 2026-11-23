<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

/**
 * LAB 3 REFERENCE SOLUTION: validated input, Query Builder (bound parameter), tenant constraint kept.
 * Note: % and _ remain LIKE wildcards in the keyword; escape them if literal matching is required.
 */
class ProjectSearchController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $keyword = $validated['q'] ?? '';

        $projects = Project::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('name', 'like', "%{$keyword}%")
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('projects.search', [
            'projects' => $projects,
            'keyword' => $keyword,
        ]);
    }
}

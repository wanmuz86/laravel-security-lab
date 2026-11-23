<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * LAB 4 REFERENCE SOLUTION (SEC-14): a token ability is not authorization. The project is resolved
 * inside the token owner's tenant and checked with the Policy, exactly as in Lab 2.
 */
class ProjectApiController extends Controller
{
    public function show(Request $request, $id)
    {
        $project = Project::query()
            ->where('organization_id', $request->user()->organization_id)
            ->findOrFail($id);

        $this->authorize('view', $project);

        return response()->json(['data' => $project->only(['id', 'name', 'description'])]);
    }
}

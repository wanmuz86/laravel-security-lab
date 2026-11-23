<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectCommentRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

/**
 * LAB 3 REFERENCE SOLUTION: validation and tenant authorization happen in the Form Request;
 * author and project are decided by the server, never by submitted identifiers.
 */
class CommentController extends Controller
{
    public function store(StoreProjectCommentRequest $request, Project $project): RedirectResponse
    {
        $project->comments()->create([
            'content' => $request->validated('content'),
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('projects.show', $project)->with('status', 'Comment posted.');
    }
}

<?php

namespace App\Policies;

use App\Models\ProjectDocument;
use App\Models\User;

/** LAB 2 REFERENCE SOLUTION: a project document inherits the tenant of its parent project. */
class ProjectDocumentPolicy
{
    public function view(User $user, ProjectDocument $document): bool
    {
        return $document->project !== null
            && $user->organization_id === $document->project->organization_id;
    }
}

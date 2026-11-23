<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/** LAB 2 REFERENCE SOLUTION: a document inherits the tenant of its parent project. */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $document->project !== null
            && $user->organization_id === $document->project->organization_id;
    }
}

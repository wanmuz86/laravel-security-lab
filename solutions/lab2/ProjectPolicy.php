<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * LAB 2 REFERENCE SOLUTION: organization ownership is checked first in every ability;
 * administrators may update/delete, members may only view.
 */
class ProjectPolicy
{
    private function sameOrganization(User $user, Project $project): bool
    {
        return $user->organization_id === $project->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project) && $user->role === 'admin';
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->sameOrganization($user, $project) && $user->role === 'admin';
    }
}

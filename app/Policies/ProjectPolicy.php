<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    /**
     * Allow GM/Admin or region-matching salesman to update a project.
     */
    public function update(User $user, Project $project): Response
    {
//        // 1️⃣ GM or Admin can edit any region
//        if ($user->hasAnyRole(['gm', 'admin'])) {
//            return Response::allow();
//        }
//
//        // 2️⃣ Region-based rule (ignore case + trim spaces)
//        $userRegion = trim((string) $user->region);
//        $projectArea = trim((string) $project->area);
//
//        if (
//            $userRegion !== '' &&
//            strcasecmp($userRegion, $projectArea) === 0
//        ) {
//            return Response::allow();
//        }
//
//        // 3️⃣ Otherwise, deny
//        return Response::deny("You are not authorized to modify this project.");
        return Response::allow();
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}

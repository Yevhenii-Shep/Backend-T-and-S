<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\User;

/** Общие проверки ролей для доступа к проектам. */
trait ChecksProjectRoleHelpers
{
    private function isAdmin(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    private function isNtiEmployee(User $user): bool
    {
        return $user->role === User::ROLE_NTI_EMPLOYEE;
    }

    private function isNtiStaff(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true);
    }

    private function isOrgAdmin(User $user): bool
    {
        return $user->role === User::ROLE_ORGANIZATION_ADMIN;
    }

    private function isOrgStaff(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
        ], true);
    }

    private function isStudent(User $user): bool
    {
        return $user->role === User::ROLE_STUDENT;
    }

    private function userOwnsProjectOrg(User $user, Project $project): bool
    {
        return $user->organization_id
            && $project->organization_id
            && (int) $user->organization_id === (int) $project->organization_id;
    }
}

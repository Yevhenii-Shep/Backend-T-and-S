<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ChecksProjectAccess
{
    /** Роли, которые могут создавать и изменять сущности проекта. */
    private function canModifyResources(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_NTI_EMPLOYEE,
        ], true);
    }

    /** Роли, которые могут деактивировать (удалять) сущности. Студент исключён. */
    private function canDeactivateResources(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
            User::ROLE_ORGANIZATION_ADMIN,
        ], true);
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        if ($project->status === Project::STATUS_INACTIVE) {
            return false;
        }

        return $this->canManageProject($user, $project);
    }

    /** Проверка доступа к записи проекта без учёта статуса (для деактивации). */
    private function canManageProject(User $user, Project $project): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return (int) $project->organization_id === (int) $user->organization_id;
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $project->team()
                ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }

    private function applyProjectVisibility(Builder $query, User $user): Builder
    {
        $query->where('status', '!=', Project::STATUS_INACTIVE);

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return $query;
        }

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return $query->where('organization_id', $user->organization_id);
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $query->whereHas(
                'team.users',
                fn ($teamUsers) => $teamUsers->where('users.id', $user->id)
            );
        }

        return $query->whereRaw('0 = 1');
    }

    /** Фильтр для дочерних сущностей (документы, этапы, аудиты) с учётом is_active и доступа к проекту. */
    private function applyProjectChildVisibility(Builder $query, User $user): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('project', fn (Builder $projectQuery) => $this->applyProjectVisibility($projectQuery, $user));
    }

    private function settableProjectStatuses(): array
    {
        return [
            Project::STATUS_PENGING,
            Project::STATUS_ACTIVE,
            Project::STATUS_DONE,
        ];
    }
}

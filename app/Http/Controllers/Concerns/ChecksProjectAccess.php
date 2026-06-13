<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Общие проверки доступа к проекту и связанным сущностям.
 */
trait ChecksProjectAccess
{
    /** Может создавать и изменять project / document / milestone / audit / evaluation. */
    private function canModifyResources(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_NTI_EMPLOYEE,
        ], true);
    }

    /** Может деактивировать сущности (студент и NTI — нет). */
    private function canDeactivateResources(User $user): bool
    {
        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
            User::ROLE_ORGANIZATION_ADMIN,
        ], true);
    }

    /** Чтение проекта: активный статус + право по роли. */
    private function canAccessProject(User $user, Project $project): bool
    {
        if ($project->status === Project::STATUS_INACTIVE) {
            return false;
        }

        return $this->canManageProject($user, $project);
    }

    /** Доступ к проекту без учёта inactive (нужно для destroy). */
    private function canManageProject(User $user, Project $project): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        if (in_array($user->role, [User::ROLE_ORGANIZATION_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE], true)) {
            return $this->canOrgAccessProject($user, $project);
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $project->team()
                ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }

    /** Org: свои проекты или грантовый инкубатор (program A — financovanie + mentoring). */
    private function canOrgAccessProject(User $user, Project $project): bool
    {
        if (!$user->organization_id) {
            return false;
        }

        if ((int) $project->organization_id === (int) $user->organization_id) {
            return true;
        }

        return (int) $project->program_type === Project::PROGRAM_TYPE_A;
    }

    /** Фильтр списка проектов по роли текущего пользователя. */
    private function applyProjectVisibility(Builder $query, User $user): Builder
    {
        $query->where('status', '!=', Project::STATUS_INACTIVE);

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return $query;
        }

        if (in_array($user->role, [User::ROLE_ORGANIZATION_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE], true)) {
            return $this->applyOrgProjectVisibility($query, $user);
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $query->whereHas(
                'team.users',
                fn ($teamUsers) => $teamUsers->where('users.id', $user->id)
            );
        }

        return $query->whereRaw('0 = 1');
    }

    /** Список проектов для org: своя организация или program A (grantový inkubačný). */
    private function applyOrgProjectVisibility(Builder $query, User $user): Builder
    {
        if (!$user->organization_id) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $orgQuery) use ($user) {
            $orgQuery
                ->where('organization_id', $user->organization_id)
                ->orWhere('program_type', Project::PROGRAM_TYPE_A);
        });
    }

    /** Фильтр document / milestone / audit: только не удалённые и с доступным проектом. */
    private function applyProjectChildVisibility(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $projectQuery) => $this->applyProjectVisibility($projectQuery, $user));
    }

    /** Фильтр evaluations: доступные проекты. */
    private function applyEvaluationVisibility(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'project',
            fn (Builder $projectQuery) => $this->applyProjectVisibility($projectQuery, $user)
        );
    }

    /** Статусы, которые можно задать через API (inactive — только через DELETE). */
    private function settableProjectStatuses(): array
    {
        return [
            Project::STATUS_PENGING,
            Project::STATUS_ACTIVE,
            Project::STATUS_DONE,
        ];
    }

    /** Допустимые типы программы проекта. */
    private function settableProgramTypes(): array
    {
        return [
            Project::PROGRAM_TYPE_A,
            Project::PROGRAM_TYPE_B,
        ];
    }

    /** Роли, которые могут быть главным аудитором. */
    private function auditorRoleIds(): array
    {
        return [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_NTI_EMPLOYEE,
        ];
    }

    /**
     * Org admin не может сужать выборку по чужой organization_id.
     * Остальные роли — без доп. ограничений (уже есть applyProjectVisibility).
     */
    private function assertCanFilterByOrganization(User $user, int $organizationId): void
    {
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN && (int) $organizationId !== (int) $user->organization_id) {
            abort(403, 'Access denied');
        }

        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE && (int) $organizationId !== (int) $user->organization_id) {
            abort(403, 'Access denied');
        }
    }
}

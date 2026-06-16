<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditEvent;
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

    /** Может создавать проект (студент — только тип A / financovanie). */
    private function canCreateProject(User $user): bool
    {
        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            return (bool) $user->organization_id;
        }

        return in_array($user->role, [
            User::ROLE_ADMIN,
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_NTI_EMPLOYEE,
            User::ROLE_STUDENT,
        ], true);
    }

    /** Допустимые program_type при создании/смене типа по роли. */
    private function creatableProgramTypesForUser(User $user): array
    {
        if ($user->role === User::ROLE_STUDENT) {
            return [Project::PROGRAM_TYPE_A];
        }

        if (in_array($user->role, [
            User::ROLE_NTI_EMPLOYEE,
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
        ], true)) {
            return [Project::PROGRAM_TYPE_B];
        }

        return $this->settableProgramTypes();
    }

    /** Назначение категории — только admin. */
    private function canAdminAssignProjectRelations(User $user): bool
    {
        return $user->role === User::ROLE_ADMIN;
    }

    /** Команда проекта не меняется после создания. */
    private function canAssignTeamToProject(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Назначить первый аудит: admin, NTI или org admin для доступного проекта.
     */
    private function canScheduleProjectAudit(User $user, Project $project): bool
    {
        if ($user->role === User::ROLE_ADMIN || $user->role === User::ROLE_NTI_EMPLOYEE) {
            return true;
        }

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN && $user->organization_id) {
            return $this->canOrgAccessProject($user, $project);
        }

        return false;
    }

    /** Главный аудитор (или admin) выставляет итог аудита после его завершения. */
    private function canSetAuditResult(User $user, AuditEvent $auditEvent): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        return $auditEvent->main_auditor && (int) $auditEvent->main_auditor === (int) $user->id;
    }

    /**
     * Привязать организацию к проекту: admin — любая org;
     * org — только своя org к доступным проектам (свои или program A без чужой org).
     */
    private function canAssignOrganizationToProject(User $user, Project $project): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        if (!in_array($user->role, [User::ROLE_ORGANIZATION_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE], true)) {
            return false;
        }

        if (!$user->organization_id || !$this->canOrgAccessProject($user, $project)) {
            return false;
        }

        if ($project->organization_id && (int) $project->organization_id !== (int) $user->organization_id) {
            return false;
        }

        return true;
    }

    /** Загрузка документов: staff с write-доступом или студент команды проекта. */
    private function canUploadProjectDocuments(User $user, Project $project): bool
    {
        if ($this->canModifyResources($user) && $this->canWriteProject($user, $project)) {
            return true;
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $this->studentBelongsToProjectTeam($user, $project);
        }

        return false;
    }

    /** Удаление документов: staff с write-доступом или студент команды проекта. */
    private function canDeleteProjectDocuments(User $user, Project $project): bool
    {
        if ($this->canModifyResources($user) && $this->canWriteProject($user, $project)) {
            return true;
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $this->studentBelongsToProjectTeam($user, $project);
        }

        return false;
    }

    /** Студент состоит в команде проекта. */
    private function studentBelongsToProjectTeam(User $user, Project $project): bool
    {
        if ($user->role !== User::ROLE_STUDENT || !$project->team_id) {
            return false;
        }

        return $project->team()
            ->whereHas('users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))
            ->exists();
    }

    /** ID активной команды студента или null. */
    private function activeTeamIdForStudent(User $user): ?int
    {
        if ($user->role !== User::ROLE_STUDENT) {
            return null;
        }

        $teamId = $user->teams()
            ->where('teams.is_active', true)
            ->wherePivotNull('leave_date')
            ->value('teams.id');

        return $teamId ? (int) $teamId : null;
    }

    /** Студент не может фильтровать проекты по чужой команде. */
    private function assertCanFilterByTeam(User $user, int $teamId): void
    {
        if ($user->role !== User::ROLE_STUDENT) {
            return;
        }

        abort_unless($this->studentBelongsToTeamId($user, $teamId), 403, 'Access denied');
    }

    /** Студент — участник команды с указанным id. */
    private function studentBelongsToTeamId(User $user, int $teamId): bool
    {
        if ($user->role !== User::ROLE_STUDENT) {
            return false;
        }

        return $user->teams()
            ->where('teams.id', $teamId)
            ->where('teams.is_active', true)
            ->wherePivotNull('leave_date')
            ->exists();
    }

    /** Назначение NTI-ментора — admin и NTI. */
    private function canAssignNtiMentor(User $user): bool
    {
        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true);
    }

    /** Назначение org-ментора — admin или org admin своей организации проекта. */
    private function canAssignOrganizationMentor(User $user, Project $project): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        if ($user->role !== User::ROLE_ORGANIZATION_ADMIN || !$user->organization_id || !$project->organization_id) {
            return false;
        }

        return (int) $user->organization_id === (int) $project->organization_id;
    }

    /** Смена статуса / дедлайна — admin или назначенный NTI-ментор проекта. */
    private function canAdminOrProjectNtiMentor(User $user, Project $project): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        return $project->mentor_from_nti && (int) $project->mentor_from_nti === (int) $user->id;
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

    /**
     * Доступ к проекту для admin/NTI без блокировки inactive.
     * Остальные роли — как canAccessProject.
     */
    private function canStaffAccessProject(User $user, Project $project): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return $this->canManageProject($user, $project);
        }

        return $this->canAccessProject($user, $project);
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
            return true;
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

    /**
     * Право изменять проект и дочерние сущности.
     * Org — только проекты своей организации (program A чужих org — только чтение).
     */
    private function canWriteProject(User $user, Project $project): bool
    {
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        if (in_array($user->role, [User::ROLE_ORGANIZATION_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE], true)) {
            if (!$user->organization_id || !$project->organization_id) {
                return false;
            }

            return (int) $project->organization_id === (int) $user->organization_id;
        }

        return false;
    }

    /** Фильтр списка проектов по роли текущего пользователя. */
    private function applyProjectVisibility(Builder $query, User $user): Builder
    {
        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            $query->where('status', '!=', Project::STATUS_INACTIVE);
        }

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_NTI_EMPLOYEE], true)) {
            return $query;
        }

        if (in_array($user->role, [User::ROLE_ORGANIZATION_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE], true)) {
            return $this->applyOrgProjectVisibility($query, $user);
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $query;
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

    /** Фильтр evaluations: проекты по видимости или свои оценки как evaluator. */
    private function applyEvaluationVisibility(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $visibilityQuery) use ($user) {
            $visibilityQuery
                ->where('evaluator_id', $user->id)
                ->orWhereHas(
                    'project',
                    fn (Builder $projectQuery) => $this->applyProjectVisibility($projectQuery, $user)
                );
        });
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

    /** Допустимые статусы при создании проекта по роли. */
    private function creatableProjectStatusesForUser(User $user): array
    {
        if (in_array($user->role, [
            User::ROLE_STUDENT,
            User::ROLE_ORGANIZATION_ADMIN,
            User::ROLE_ORGANIZATION_EMPLOYEE,
        ], true)) {
            return [
                Project::STATUS_PENGING,
                Project::STATUS_ACTIVE,
            ];
        }

        return $this->settableProjectStatuses();
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

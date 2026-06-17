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
    use ChecksProjectRoleHelpers;

    // --- Роли: кто может создавать и менять сущности проекта ---

    private function canModifyResources(User $user): bool
    {
        return $this->isNtiStaff($user) || $this->isOrgStaff($user);
    }

    private function canCreateProject(User $user): bool
    {
        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            return (bool) $user->organization_id;
        }

        return $this->isStudent($user) || $this->isNtiStaff($user) || $this->isOrgAdmin($user);
    }

    private function creatableProgramTypesForUser(User $user): array
    {
        if ($this->isStudent($user)) {
            return [Project::PROGRAM_TYPE_A];
        }

        if ($this->isOrgStaff($user) || $this->isNtiEmployee($user)) {
            return [Project::PROGRAM_TYPE_B];
        }

        return $this->settableProgramTypes();
    }

    private function canAssignTeamToProject(User $user, Project $project): bool
    {
        return (int) $project->program_type === Project::PROGRAM_TYPE_B && $this->isNtiStaff($user);
    }

    // --- Аудит ---

    private function canSetAuditResult(User $user, AuditEvent $auditEvent): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $auditEvent->main_auditor && (int) $auditEvent->main_auditor === (int) $user->id;
    }

    private function getCompletedAudit(Project $project): ?AuditEvent
    {
        return $project->auditEvents()
            ->whereNotNull('result')
            //->where('end_time', '<=', now())
            ->first();
    }

    private function canOrgDecideProjectAfterAudit(User $user, Project $project): bool
    {
        return $this->isOrgStaff($user)
            && $this->canOrgAccessProject($user, $project)
            && $project->status === Project::STATUS_PENGING
            && $this->getCompletedAudit($project) !== null;
    }

    private function canOrgAcceptProjectAfterAudit(User $user, Project $project): bool
    {
        if (!$this->canOrgDecideProjectAfterAudit($user, $project)) {
            return false;
        }

        $audit = $this->getCompletedAudit($project);

        return $audit && (int) $audit->result === AuditEvent::RESULT_ACCEPTED;
    }

    // --- Организация ---

    private function canAssignOrganizationToProject(User $user, Project $project): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$this->isOrgStaff($user) || !$this->canOrgAccessProject($user, $project)) {
            return false;
        }

        return !$project->organization_id || $this->userOwnsProjectOrg($user, $project);
    }

    private function canManageProjectDocuments(User $user, Project $project): bool
    {
        if ($this->canModifyResources($user) && $this->canWriteProject($user, $project)) {
            return true;
        }

        return $this->isStudent($user) && $this->studentBelongsToProjectTeam($user, $project);
    }

    private function studentBelongsToProjectTeam(User $user, Project $project): bool
    {
        if (!$this->isStudent($user) || !$project->team_id) {
            return false;
        }

        return $project->team()
            ->whereHas('users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))
            ->exists();
    }

    private function activeTeamIdForStudent(User $user): ?int
    {
        if (!$this->isStudent($user)) {
            return null;
        }

        $teamId = $user->teams()
            ->where('teams.is_active', true)
            ->wherePivotNull('leave_date')
            ->value('teams.id');

        return $teamId ? (int) $teamId : null;
    }

    private function assertCanFilterByTeam(User $user, int $teamId): void
    {
        if (!$this->isStudent($user)) {
            return;
        }

        abort_unless($this->studentBelongsToTeamId($user, $teamId), 403, 'Access denied');
    }

    private function studentBelongsToTeamId(User $user, int $teamId): bool
    {
        if (!$this->isStudent($user)) {
            return false;
        }

        return $user->teams()
            ->where('teams.id', $teamId)
            ->where('teams.is_active', true)
            ->wherePivotNull('leave_date')
            ->exists();
    }

    private function canAssignOrganizationMentor(User $user, Project $project): bool
    {
        return $this->isAdmin($user)
            || ($this->isOrgAdmin($user) && $this->userOwnsProjectOrg($user, $project));
    }

    private function canAdminOrProjectNtiMentor(User $user, Project $project): bool
    {
        return $this->isAdmin($user)
            || ($project->mentor_from_nti && (int) $project->mentor_from_nti === (int) $user->id);
    }

    private function canDeactivateResources(User $user): bool
    {
        return $this->isAdmin($user) || $this->isOrgStaff($user);
    }

    // --- Доступ к проекту ---

    private function canAccessProject(User $user, Project $project): bool
    {
        if ($project->status === Project::STATUS_INACTIVE) {
            return false;
        }

        return $this->canManageProject($user, $project);
    }

    private function canStaffAccessProject(User $user, Project $project): bool
    {
        if ($this->isNtiStaff($user)) {
            return $this->canManageProject($user, $project);
        }

        return $this->canAccessProject($user, $project);
    }

    private function canManageProject(User $user, Project $project): bool
    {
        if ($this->isNtiStaff($user)) {
            return true;
        }

        if ($this->isOrgStaff($user)) {
            return $this->canOrgAccessProject($user, $project);
        }

        return $this->isStudent($user);
    }

    private function canOrgAccessProject(User $user, Project $project): bool
    {
        if (!$user->organization_id) {
            return false;
        }

        if ($this->userOwnsProjectOrg($user, $project)) {
            return true;
        }

        return (int) $project->program_type === Project::PROGRAM_TYPE_A;
    }

    private function canWriteProject(User $user, Project $project): bool
    {
        if ($this->isNtiStaff($user)) {
            return true;
        }

        return $this->isOrgStaff($user) && $this->userOwnsProjectOrg($user, $project);
    }

    private function applyProjectVisibility(Builder $query, User $user): Builder
    {
        if (!$this->isNtiStaff($user)) {
            $query->where('status', '!=', Project::STATUS_INACTIVE);
        }

        if ($this->isNtiStaff($user)) {
            return $query;
        }

        if ($this->isOrgStaff($user)) {
            return $this->applyOrgProjectVisibility($query, $user);
        }

        if ($this->isStudent($user)) {
            return $query;
        }

        return $query->whereRaw('0 = 1');
    }

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

    private function applyProjectChildVisibility(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            'project',
            fn (Builder $projectQuery) => $this->applyProjectVisibility($projectQuery, $user)
        );
    }

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

    private function settableProjectStatuses(): array
    {
        return [
            Project::STATUS_PENGING,
            Project::STATUS_ACTIVE,
            Project::STATUS_DONE,
        ];
    }

    private function settableProgramTypes(): array
    {
        return [
            Project::PROGRAM_TYPE_A,
            Project::PROGRAM_TYPE_B,
        ];
    }

    private function assertCanFilterByOrganization(User $user, int $organizationId): void
    {
        if ($this->isOrgStaff($user) && (int) $organizationId !== (int) $user->organization_id) {
            abort(403, 'Access denied');
        }
    }
}

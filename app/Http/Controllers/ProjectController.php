<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD проектов с проверкой доступа по ролям.
 */
class ProjectController extends Controller
{
    use ChecksProjectAccess;

    /** Связи для списка и карточки проекта (имена в ProjectResource). */
    private function projectListRelations(): array
    {
        return ['team', 'organization', 'category', 'ntiMentor', 'organizationMentor'];
    }

    /** Связи для show/update с вложенными сущностями. */
    private function projectDetailRelations(): array
    {
        return [
            ...$this->projectListRelations(),
            'team.users',
            'documents.project',
            'milestones.project',
            'auditEvents.project',
            'auditEvents.mainAuditor',
            'auditEvents.participants.user',
            'evaluations.project',
            'evaluations.evaluator',
        ];
    }

    /**
     * GET /api/projects — список доступных проектов.
     * Фильтры: status, organization_id, team_id, category_id.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Project::query()
            ->with($this->projectListRelations());

        $this->applyProjectVisibility($query, $user);

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        if ($request->filled('organization_id')) {
            $organizationId = $request->integer('organization_id');
            $this->assertCanFilterByOrganization($user, $organizationId);
            $query->where('organization_id', $organizationId);
        }

        if ($request->filled('team_id')) {
            $teamId = $request->integer('team_id');
            $this->assertCanFilterByTeam($user, $teamId);
            $query->where('team_id', $teamId);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return ProjectResource::collection($query->get());
    }

    /**
     * POST /api/projects — создание проекта.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canCreateProject($user), 403, 'Access denied');

        $teamIdRules = $user->role === User::ROLE_STUDENT
            ? ['prohibited']
            : ['nullable', 'integer', 'exists:teams,id'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:projects,slug'],
            'team_id' => $teamIdRules,
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['required', 'integer', Rule::in($this->creatableProgramTypesForUser($user))],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['prohibited'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            abort_unless($user->organization_id, 422, 'You must belong to an organization to create a project.');
        }

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            $data['organization_id'] = $user->organization_id;
        }

        if ($user->role === User::ROLE_STUDENT) {
            $teamId = $this->activeTeamIdForStudent($user);
            abort_unless($teamId, 422, 'You must belong to an active team to create a project.');
            $data['team_id'] = $teamId;
        }

        $data['status'] = Project::STATUS_PENGING;

        $project = Project::create($data);

        return (new ProjectResource(
            $project->load($this->projectListRelations())
        ))->response()->setStatusCode(201);
    }

    /**
     * GET /api/projects/{project} — проект с документами, этапами и аудитами.
     */
    public function show(Request $request, Project $project)
    {
        abort_unless($this->canStaffAccessProject($request->user(), $project), 403, 'Access denied');

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PUT/PATCH /api/projects/{project} — обновление проекта.
     */
    public function update(Request $request, Project $project)
    {
        $user = $request->user();

        if ($user->role === User::ROLE_STUDENT) { // Студент если это проект его команды
            $activeUsersTeam = $user->teams()
                ->where('teams.is_active', true)
                ->first();
            if (!$activeUsersTeam) abort(403, 'Accesss denied');
            abort_unless($project->team_id === $activeUsersTeam->id, 403, 'Accesss denied');
        } else { // Все остальные
            abort_unless($this->canModifyResources($user), 403, 'Access denied');
            abort_unless($this->canWriteProject($user, $project), 403, 'Access denied');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($project->id)],
            'description' => ['nullable', 'string'],
        ]);

        $project->update($data);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * DELETE /api/projects/{project} — деактивация (status = inactive).
     */
    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canDeactivateResources($user), 403, 'Access denied');
        abort_if($project->status === Project::STATUS_INACTIVE, 404);
        abort_unless($this->canWriteProject($user, $project), 403, 'Access denied');

        $project->update(['status' => Project::STATUS_INACTIVE]);

        return response()->noContent();
    }

    /**
     * PATCH /api/projects/{project}/status — смена статуса (pending / active / done).
     */
    public function updateStatus(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAdminOrProjectNtiMentor($user, $project), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'status' => ['required', 'integer', Rule::in($this->settableProjectStatuses())],
        ]);

        $project->update(['status' => $data['status']]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/deadline — смена дедлайна.
     */
    public function updateDeadline(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAdminOrProjectNtiMentor($user, $project), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'deadline' => ['nullable', 'date'],
        ]);

        $project->update(['deadline' => $data['deadline'] ?? null]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/assign-team — назначить проект команде (или отвязать).
     */
    public function assignTeam(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAssignTeamToProject($user, $project), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $project->update(['team_id' => $data['team_id'] ?? null]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/assign-organization — назначить проект организации (или отвязать).
     */
    public function assignOrganization(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAssignOrganizationToProject($user, $project), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $organizationId = $data['organization_id'] ?? null;

        if (in_array($user->role, [User::ROLE_ORGANIZATION_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE], true)) {
            $organizationId = $user->organization_id;
        }

        $update = ['organization_id' => $organizationId];

        if ($project->mentor_from_organization) {
            $mentor = User::find($project->mentor_from_organization);
            if (
                !$organizationId
                || !$mentor
                || (int) $mentor->organization_id !== (int) $organizationId
            ) {
                $update['mentor_from_organization'] = null;
            }
        }

        $project->update($update);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/assign-category — назначить категорию проекту.
     */
    public function assignCategory(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAdminAssignProjectRelations($user), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $project->update(['category_id' => $data['category_id']]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/assign-nti-mentor — назначить NTI-ментора (или отвязать).
     */
    public function assignNtiMentor(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAssignNtiMentor($user), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $mentorId = $data['mentor_from_nti'] ?? null;
        $this->assertValidNtiMentor($mentorId);

        $project->update(['mentor_from_nti' => $mentorId]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/assign-organization-mentor — назначить ментора организации (или отвязать).
     */
    public function assignOrganizationMentor(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canAssignOrganizationMentor($user, $project), 403, 'Access denied');
        abort_unless($this->canStaffAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $mentorId = $data['mentor_from_organization'] ?? null;
        $this->assertValidOrganizationMentor($project, $mentorId);

        $project->update(['mentor_from_organization' => $mentorId]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/accept-after-audit — организация принимает проект после успешного аудита.
     */
    public function acceptAfterAudit(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canOrgAcceptProjectAfterAudit($user, $project), 403, 'Access denied');

        $project->update([
            'status' => Project::STATUS_ACTIVE,
            'organization_id' => $user->organization_id,
        ]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /**
     * PATCH /api/projects/{project}/decline-after-audit — организация отклоняет проект после успешного аудита.
     */
    public function declineAfterAudit(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canOrgDeclineProjectAfterAudit($user, $project), 403, 'Access denied');

        $project->update(['status' => Project::STATUS_PENGING]);

        return new ProjectResource(
            $project->load($this->projectDetailRelations())
        );
    }

    /** Студент может создавать проект только для своей команды. */
    private function assertStudentBelongsToTeam(User $user, int $teamId): void
    {
        $belongsToTeam = Team::query()
            ->where('id', $teamId)
            ->whereHas('users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))
            ->exists();

        if (!$belongsToTeam) {
            throw ValidationException::withMessages([
                'team_id' => ['You must belong to the selected team.'],
            ]);
        }
    }

    /** Проверка NTI-ментора. */
    private function assertValidNtiMentor(?int $mentorId): void
    {
        if ($mentorId === null) {
            return;
        }

        $mentor = User::find($mentorId);
        if (!$mentor || $mentor->role !== User::ROLE_NTI_EMPLOYEE) {
            throw ValidationException::withMessages([
                'mentor_from_nti' => ['Mentor must be an NTI employee.'],
            ]);
        }
    }

    /** Проверка org-ментора: только сотрудник/admin той же организации, что и проект. */
    private function assertValidOrganizationMentor(Project $project, ?int $mentorId): void
    {
        if ($mentorId === null) {
            return;
        }

        if (!$project->organization_id) {
            throw ValidationException::withMessages([
                'mentor_from_organization' => ['Project must be assigned to an organization first.'],
            ]);
        }

        $mentor = User::find($mentorId);
        if (
            !$mentor
            || !in_array($mentor->role, [User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true)
            || (int) $mentor->organization_id !== (int) $project->organization_id
        ) {
            throw ValidationException::withMessages([
                'mentor_from_organization' => ['Mentor must belong to the project organization.'],
            ]);
        }
    }
}

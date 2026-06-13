<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksProjectAccess;
use App\Models\Project;
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

    /**
     * GET /api/projects — список доступных проектов.
     * Фильтры: status, organization_id, team_id, category_id.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Project::query()
            ->with(['team', 'organization', 'category']);

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
            $query->where('team_id', $request->integer('team_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return response()->json($query->get());
    }

    /**
     * POST /api/projects — создание проекта.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['required', 'integer', Rule::in($this->settableProgramTypes())],
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['required', 'integer', Rule::in($this->settableProjectStatuses())],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            $data['organization_id'] = $user->organization_id;
        }

        $this->assertValidMentors($data);

        $project = Project::create($data);

        return response()->json(
            $project->load(['team', 'organization', 'category']),
            201
        );
    }

    /**
     * GET /api/projects/{project} — проект с документами, этапами и аудитами.
     */
    public function show(Request $request, Project $project)
    {
        abort_unless($this->canAccessProject($request->user(), $project), 403, 'Access denied');

        return response()->json(
            $project->load([
                'team',
                'organization',
                'category',
                'documents',
                'milestones',
                'auditEvents',
                'evaluations.evaluator',
            ])
        );
    }

    /**
     * PUT/PATCH /api/projects/{project} — обновление проекта.
     */
    public function update(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless($this->canModifyResources($user), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($project->id)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['sometimes', 'required', 'integer', Rule::in($this->settableProgramTypes())],
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', 'required', 'integer', Rule::in($this->settableProjectStatuses())],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        if ($user->role === User::ROLE_ORGANIZATION_EMPLOYEE) {
            $data['organization_id'] = $user->organization_id;
        }

        $merged = array_merge($project->only([
            'mentor_from_nti',
            'mentor_from_organization',
            'organization_id',
        ]), $data);

        $this->assertValidMentors($merged);

        $project->update($data);

        return response()->json(
            $project->load([
                'team',
                'organization',
                'category',
                'documents',
                'milestones',
                'auditEvents',
                'evaluations.evaluator',
            ])
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
        abort_unless($this->canManageProject($user, $project), 403, 'Access denied');

        $project->update(['status' => Project::STATUS_INACTIVE]);

        return response()->noContent();
    }

    /** Проверка ролей менторов и принадлежности org-ментора к организации проекта. */
    private function assertValidMentors(array $data): void
    {
        if (!empty($data['mentor_from_nti'])) {
            $mentor = User::find($data['mentor_from_nti']);
            if (!$mentor || $mentor->role !== User::ROLE_NTI_EMPLOYEE) {
                throw ValidationException::withMessages([
                    'mentor_from_nti' => ['Mentor must be an NTI employee.'],
                ]);
            }
        }

        if (!empty($data['mentor_from_organization'])) {
            $mentor = User::find($data['mentor_from_organization']);
            $orgId = $data['organization_id'] ?? null;

            if (
                !$mentor
                || !in_array($mentor->role, [User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true)
                || ($orgId && (int) $mentor->organization_id !== (int) $orgId)
            ) {
                throw ValidationException::withMessages([
                    'mentor_from_organization' => ['Mentor must belong to the project organization.'],
                ]);
            }
        }
    }
}

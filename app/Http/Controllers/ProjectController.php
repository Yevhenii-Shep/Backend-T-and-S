<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Project::query()->with(['team', 'organization', 'category']);

        // Внутренние роли видят полный реестр проектов.
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return response()->json($query->get());
        }

        // Админ организации ограничен только своей организацией.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return response()->json(
                $query->where('organization_id', $user->organization_id)->get()
            );
        }

        // Студент видит только проекты своей команды.
        if ($user->role === User::ROLE_STUDENT) {
            return response()->json(
                $query->whereHas('team.users', fn ($teamUsers) => $teamUsers->where('users.id', $user->id))->get()
            );
        }

        return response()->json(collect());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['required', 'integer'],
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'status' => ['required', 'integer'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        // Админ организации не может создавать проекты чужой организации.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        $project = Project::create($data);

        return response()->json(
            $project->load(['team', 'organization', 'category']),
            201
        );
    }

    public function show(Request $request, Project $project)
    {
        abort_unless($this->canAccessProject($request->user(), $project), 403, 'Access denied');

        return response()->json(
            $project->load(['team', 'organization', 'category', 'documents', 'milestones', 'auditEvents'])
        );
    }

    public function update(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($project->id)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'program_type' => ['sometimes', 'required', 'integer'],
            'mentor_from_nti' => ['nullable', 'integer', 'exists:users,id'],
            'mentor_from_organization' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', 'required', 'integer'],
            'description' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        // Для админа организации владелец проекта всегда фиксирован.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            $data['organization_id'] = $user->organization_id;
        }

        $project->update($data);

        return response()->json(
            $project->load(['team', 'organization', 'category', 'documents', 'milestones', 'auditEvents'])
        );
    }

    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $project->delete();

        return response()->noContent();
    }

    private function canAccessProject(User $user, Project $project): bool
    {
        // Роли с глобальным доступом.
        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_NTI_EMPLOYEE], true)) {
            return true;
        }

        // Админ организации работает только со своими проектами.
        if ($user->role === User::ROLE_ORGANIZATION_ADMIN) {
            return (int) $project->organization_id === (int) $user->organization_id;
        }

        // Доступ студента определяется членством в команде.
        if ($user->role === User::ROLE_STUDENT) {
            return $project->team()
                ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }
}

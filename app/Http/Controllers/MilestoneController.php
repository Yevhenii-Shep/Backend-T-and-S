<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index(Request $request)
    {
        $query = Milestone::query()->with('project');

        // Опциональный фильтр по проекту.
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'integer', 'in:0,1,2,3'],
            'deadline' => ['nullable', 'date'],
        ]);

        // Перед созданием повторно проверяем доступ через проект.
        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');

        $milestone = Milestone::create($data);

        return response()->json($milestone->load('project'), 201);
    }

    public function show(Request $request, Milestone $milestone)
    {
        abort_unless($this->canAccessProject($request->user(), $milestone->project), 403, 'Access denied');

        return response()->json($milestone->load('project'));
    }

    public function update(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN, User::ROLE_NTI_EMPLOYEE], true), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $milestone->project), 403, 'Access denied');

        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'integer', 'in:0,1,2,3'],
            'deadline' => ['nullable', 'date'],
        ]);

        if (isset($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            abort_unless($this->canAccessProject($user, $project), 403, 'Access denied');
        }

        $milestone->update($data);

        return response()->json($milestone->load('project'));
    }

    public function destroy(Request $request, Milestone $milestone)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [User::ROLE_ADMIN, User::ROLE_ORGANIZATION_EMPLOYEE, User::ROLE_ORGANIZATION_ADMIN], true), 403, 'Access denied');
        abort_unless($this->canAccessProject($user, $milestone->project), 403, 'Access denied');

        $milestone->delete();

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
